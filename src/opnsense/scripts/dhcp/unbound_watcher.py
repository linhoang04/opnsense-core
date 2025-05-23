#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2020 Ad Schellevis <ad@opnsense.org>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------
    watch dhcp lease file and build include file for unbound
"""
import ipaddress
import os
import sys
import subprocess
import time
import tempfile
import argparse
import syslog
import re
from configparser import ConfigParser
sys.path.insert(0, "/usr/local/opnsense/site-python")
from daemonize import Daemonize
import watchers.dhcpd


def unbound_control(commands, input=None, output_stream=None):
    """ execute (chrooted) unbound-control command
        :param commands: command list (parameters)
        :param input: (optional ) list of lines to be sent to input stream
        :param output_stream: (optional)output stream
        :return: None
    """
    input_string=None
    if input:
        nl='\n'
        input_string = f'{nl.join(input)}{nl}'

    # XXX invoke can fail (non-zero exit code), but unclear why and what to do
    subprocess.run(['/usr/sbin/chroot', '-u', 'unbound', '-g', 'unbound', '/',
                    '/usr/local/sbin/unbound-control', '-c', '/var/unbound/unbound.conf'] + commands,
                   input=input_string, stdout=output_stream, stderr=subprocess.STDOUT, text=True)

    if output_stream:
        output_stream.seek(0)

class UnboundLocalData:
    def __init__(self):
        self._map_by_address = dict()
        self._map_by_fqdn = dict()
        with tempfile.NamedTemporaryFile() as output_stream:
            unbound_control(['list_local_data'], output_stream=output_stream)
            for line in output_stream:
                parts = line.decode().split()
                if len(parts) > 4 and parts[3] == 'A' and parts[4] != '0.0.0.0':
                    self.add_address(parts[4], parts[0][:-1])

    def add_address(self, address, fqdn):
        if address not in self._map_by_address:
            self._map_by_address[address] = list()
        self._map_by_address[address].append(fqdn)
        if fqdn not in self._map_by_fqdn:
            self._map_by_fqdn[fqdn] = list()
        self._map_by_fqdn[fqdn].append(address)

    def all_fqdns(self, address, fqdn):
        result = set()
        if address in self._map_by_address:
            for unbfqdn in self._map_by_address[address]:
                result.add(unbfqdn)
        if fqdn in self._map_by_fqdn:
            result.add(fqdn)
        return result

    def cleanup(self, address, fqdn):
        if address in self._map_by_address:
            for rfqdn in self._map_by_address[address]:
                if rfqdn in self._map_by_fqdn and address in self._map_by_fqdn[rfqdn]:
                    self._map_by_fqdn[rfqdn].remove(address)
                    if len(self._map_by_fqdn[rfqdn]) == 0:
                        del self._map_by_fqdn[rfqdn]
            del self._map_by_address[address]

        if fqdn in self._map_by_fqdn and address in self._map_by_fqdn[fqdn]:
            self._map_by_fqdn[fqdn].remove(address)
            if len(self._map_by_fqdn[fqdn]) == 0:
                del self._map_by_fqdn[fqdn]

    def is_equal(self, address, fqdn):
        return fqdn in self._map_by_fqdn and address in self._map_by_fqdn[fqdn]

    def fqdn_addresses(self, fqdn):
        return self._map_by_fqdn[fqdn] if fqdn in self._map_by_fqdn else []

    def fqdn_addresses(self, fqdn):
        return self._map_by_fqdn[fqdn] if fqdn in self._map_by_fqdn else []

def run_watcher(target_filename, default_domain, watch_file, config):
    # cleanup interval (seconds)
    cleanup_interval = 60

    # parse lease configs (ranges and domain names)
    lease_configs = list()
    if os.path.isfile(config):
        cnf = ConfigParser()
        cnf.read(config)
        for section in cnf.sections():
            if cnf.has_option(section, 'start') and cnf.has_option(section, 'end') and cnf.has_option(section, 'domain'):
                try:
                    lease_configs.append({
                        'start': ipaddress.ip_address(cnf.get(section, 'start')),
                        'end': ipaddress.ip_address(cnf.get(section, 'end')),
                        'domain': cnf.get(section, 'domain')
                    })
                except ValueError:
                    pass

    # initiate lease watcher and setup cache
    dhcpdleases = watchers.dhcpd.DHCPDLease(watch_file)
    cached_leases = dict()
    unbound_local_data = UnboundLocalData()
    hostname_pattern = re.compile("(?!-)[A-Z0-9-_]*(?<!-)$", re.IGNORECASE)

    # start watching dhcp leases
    last_cleanup = time.time()
    while True:
        dhcpd_changed = False
        for lease in dhcpdleases.watch():
            if 'ends' in lease and lease['ends'] > time.time() \
                    and 'client-hostname' in lease and 'address' in lease and lease['client-hostname']:
                if all(hostname_pattern.match(part) for part in lease['client-hostname'].strip('.').split('.')):
                    address = ipaddress.ip_address(lease['address'])
                    lease['domain'] = default_domain
                    for lease_config in lease_configs:
                        if lease_config['start'] <= address <= lease_config['end']:
                            lease['domain'] = lease_config['domain']
                    cached_leases[lease['address']] = lease
                    dhcpd_changed = True
                else:
                    syslog.syslog(
                        syslog.LOG_WARNING,
                        "dhcpd leases: %s not a valid hostname, ignoring" % lease['client-hostname']
                    )

        remove_rr = list()
        add_rr = list()
        if time.time() - last_cleanup > cleanup_interval:
            # cleanup every x seconds
            last_cleanup = time.time()
            addresses = list(cached_leases)
            for address in addresses:
                if cached_leases[address]['ends'] < time.time():
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "dhcpd expired %s @ %s" % (cached_leases[address]['client-hostname'], address)
                    )
                    fqdn = '%s.%s' % (cached_leases[address]['client-hostname'], cached_leases[address]['domain'])
                    remove_rr += [ ipaddress.ip_address(address).reverse_pointer,
                                   fqdn ]
                    if unbound_local_data.is_equal(address, fqdn):
                        unbound_local_data.cleanup(address, fqdn)

                        # same fqdn may be hooked to other (new) hosts, reinject the existing ones after removal
                        for addr in unbound_local_data.fqdn_addresses(fqdn):
                            add_rr.append(f'{fqdn} IN A {addr}')

                    del cached_leases[address]
                    dhcpd_changed = True

        if dhcpd_changed:
            # dump dns output to target (used on initial startup, unbound_control is used as live feed)
            with open(target_filename, 'w') as unbound_conf:
                for address in cached_leases:
                    unbound_conf.write('local-data-ptr: "%s %s.%s"\n' % (
                        address, cached_leases[address]['client-hostname'], cached_leases[address]['domain'])
                    )
                    unbound_conf.write('local-data: "%s.%s IN A %s"\n' % (
                        cached_leases[address]['client-hostname'], cached_leases[address]['domain'], address)
                    )
            # signal unbound
            for address in cached_leases:
                fqdn = '%s.%s' % (cached_leases[address]['client-hostname'], cached_leases[address]['domain'])
                if not unbound_local_data.is_equal(address, fqdn):
                    remove_rr.append(ipaddress.ip_address(address).reverse_pointer)
                    for tmp_fqdn in unbound_local_data.all_fqdns(address, fqdn):
                        syslog.syslog(syslog.LOG_NOTICE, 'dhcpd entry changed %s @ %s.' % (tmp_fqdn, address))
                        remove_rr.append(tmp_fqdn)
                    unbound_local_data.cleanup(address, fqdn)
                    add_rr += [ f'{ipaddress.ip_address(address).reverse_pointer} PTR {fqdn}',
                                f'{fqdn} IN A {address}' ]
                    unbound_local_data.add_address(address, fqdn)

        # Updated unbound
        if remove_rr:
            unbound_control(['local_datas_remove'], input=remove_rr)
        if add_rr:
            unbound_control(['local_datas'], input=add_rr)

        # wait for next cycle
        time.sleep(1)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--pid', help='pid file location', default='/var/run/unbound_dhcpd.pid')
    parser.add_argument('--source', help='source leases file',
                                    default='/var/dhcpd/var/db/dhcpd.leases')
    parser.add_argument('--target', help='target config file, used when unbound restarts',
                                    default='/var/unbound/dhcpleases.conf')
    parser.add_argument('--foreground', help='run in foreground', default=False, action='store_true')
    parser.add_argument('--domain', help='default domain to use',  default='local')
    parser.add_argument('--config', help='configuration file to use',  default='/usr/local/etc/unbound_dhcpd.conf')

    inputargs = parser.parse_args()

    syslog.openlog('unbound', facility=syslog.LOG_LOCAL4)

    if inputargs.foreground:
        run_watcher(
            target_filename=inputargs.target,
            default_domain=inputargs.domain,
            watch_file=inputargs.source,
            config=inputargs.config
        )
    else:
        syslog.syslog(syslog.LOG_NOTICE, 'daemonize unbound dhcpd watcher.')
        cmd  = lambda : run_watcher(
            target_filename=inputargs.target,
            default_domain=inputargs.domain,
            watch_file=inputargs.source,
            config=inputargs.config
        )
        daemon = Daemonize(app="unbound_dhcpd", pid=inputargs.pid, action=cmd)
        daemon.start()
