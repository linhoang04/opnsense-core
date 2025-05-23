#!/usr/local/bin/python3

"""
    Copyright (c) 2024 DynFi
    Copyright (c) 2019-2020 Ad Schellevis <ad@opnsense.org>
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

    query log files
"""

import sys
import os.path
import re
import ujson
import datetime
import glob

from logformats import FormatContainer, BaseLogFormat
sys.path.insert(0, "/usr/local/opnsense/site-python")
from log_helper import reverse_log_reader
import argparse

if __name__ == '__main__':
    # handle parameters
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', help='output type [json/text]', default='json')
    parser.add_argument('--filter', help='filter results', default='')
    parser.add_argument('--limit', help='limit number of results', default='')
    parser.add_argument('--offset', help='begin at row number', default='')
    parser.add_argument('--filename', help='log file name (excluding .log extension)', default='')
    parser.add_argument('--module', help='module', default='core')
    parser.add_argument('--severity', help='comma separated list of severities', default='')
    inputargs = parser.parse_args()

    result = {'filters': inputargs.filter, 'rows': [], 'total_rows': 0, 'origin': os.path.basename(inputargs.filename)}
    is_suricata = False
    if inputargs.filename != "":
        log_filenames = list()
        if inputargs.module == 'core':
            log_basename = "/var/log/%s" % os.path.basename(inputargs.filename)
        elif inputargs.module.startswith('suricata'):
            is_suricata = True
            log_basename = "/var/log/suricata/%s/%s" % (
                os.path.basename(inputargs.module), os.path.basename(inputargs.filename)
            )
        else:
            log_basename = "/var/log/%s/%s" % (
                os.path.basename(inputargs.module), os.path.basename(inputargs.filename)
            )
        if os.path.isdir(log_basename):
            # new syslog-ng local targets use an extra directory level
            filenames = glob.glob("%s/*.log" % log_basename) if is_suricata else glob.glob("%s/%s_*.log" % (log_basename, log_basename.split('/')[-1].split('.')[0]))
            for filename in sorted(filenames, reverse=True):
                log_filenames.append(filename)
        # legacy log output is always stashed last
        log_filenames.append("%s.log" % log_basename)
        if inputargs.module != 'core':
            log_filenames.append("/var/log/%s_%s.log" % (inputargs.module, os.path.basename(inputargs.filename)))
        limit = int(inputargs.limit) if inputargs.limit.isdigit()  else 0
        offset = int(inputargs.offset) if inputargs.offset.isdigit() else 0
        severity = inputargs.severity.split(',') if inputargs.severity.strip() != '' else []

        filter = inputargs.filter.replace('*', '.*').lower()
        if filter.find('*') == -1:
            # no wildcard operator, assume partial match
            filter = ".*%s.*" % filter
        filter_regexp = re.compile(filter)

        row_number = 0
        for filename in log_filenames:
            if os.path.exists(filename):
                format_container = FormatContainer(filename)
                for rec in reverse_log_reader(filename):
                    row_number += 1
                    if rec['line'] != "" and filter_regexp.match(('%s' % rec['line']).lower()):
                        frmt = format_container.get_format(rec['line'])
                        record = {
                            'timestamp': None,
                            'parser': None,
                            'facility': 1,
                            'severity': None,
                            'process_name': '',
                            'pid': None,
                            'rnum': row_number
                        }
                        if frmt:
                            if issubclass(frmt.__class__, BaseLogFormat):
                                # backwards compatibility, old style log handler
                                record['timestamp'] = frmt.timestamp(rec['line'])
                                record['process_name'] = frmt.process_name(rec['line'])
                                record['line'] = frmt.line(rec['line'])
                                record['parser'] = frmt.name
                            else:
                                record['timestamp'] = frmt.timestamp
                                record['process_name'] = frmt.process_name
                                record['pid'] = frmt.pid
                                record['facility'] = frmt.facility
                                record['severity'] = frmt.severity_str
                                record['line'] = frmt.line
                                record['parser'] = frmt.name
                        else:
                            record['line'] = rec['line']
                        if len(severity) == 0 or record['severity'] is None or record['severity'] in severity:
                            result['total_rows'] += 1
                            if (len(result['rows']) < limit or limit == 0) and result['total_rows'] >= offset:
                                if inputargs.output == 'json':
                                    result['rows'].append(record)
                                else:
                                    print("%(timestamp)s\t%(severity)s\t%(process_name)s\t%(line)s" % record)
                            elif limit > 0 and result['total_rows'] > offset + limit:
                                # do not fetch data until end of file...
                                break

            if limit > 0 and result['total_rows'] > offset + limit:
                break

    # output results (when json)
    if inputargs.output == 'json':
        print(ujson.dumps(result))

