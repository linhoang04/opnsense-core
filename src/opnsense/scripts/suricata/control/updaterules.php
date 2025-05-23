#!/usr/local/bin/php

<?php
/*
 * Copyright (C) 2023 DynFi
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("config.inc");
require_once("util.inc");
require_once("plugins.inc.d/suricata.inc");

global $notify_message, $config;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$suricata_rules_dir = SURICATA_RULES_DIR;
$suri_eng_ver = filter_var(SURICATA_BIN_VERSION, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

/* define checks */
$oinkid = $config['OPNsense']['Suricata']['global']['oinkcode'];
$snort_filename = $config['OPNsense']['Suricata']['global']['snortrulesfile'];
$etproid = $config['OPNsense']['Suricata']['global']['etprocode'];
$snortdownload = $config['OPNsense']['Suricata']['global']['enablevrtrules'] == '1' ? '1' : '0';
$etpro = $config['OPNsense']['Suricata']['global']['enableetprorules'] == '1' ? '1' : '0';
$eto = $config['OPNsense']['Suricata']['global']['enableetopenrules'] == '1' ? '1' : '0';
$vrt_enabled = $config['OPNsense']['Suricata']['global']['enablevrtrules'] == '1' ? '1' : '0';
$snortcommunityrules = $config['OPNsense']['Suricata']['global']['snortcommunityrules'] == '1' ? '1' : '0';
$feodotracker_rules = $config['OPNsense']['Suricata']['global']['enablefeodobotnetc2rules'] == '1' ? '1' : '0';
$sslbl_rules = $config['OPNsense']['Suricata']['global']['enableabusesslblacklistrules'] == '1' ? '1' : '0';
$enable_extra_rules = $config['OPNsense']['Suricata']['global']['enableextrarules'] == '1' ? '1' : '0';

//init_config_arr(array('installedpackages', 'suricata' ,'config', 0, 'extrarules', 'rule'));
$extra_rules = array(); // TODO $config['OPNsense']['Suricata']['global']['extrarules']['rule'];

/* Working directory for downloaded rules tarballs */
$tmpfname = "/tmp/suricata_rules_up";

/* Snort Rules filenames and URL */
if ($config['OPNsense']['Suricata']['global']['enablesnortcustomurl'] == '1') {
    $snort_rule_url = trim(substr($config['OPNsense']['Suricata']['global']['snortcustom_rl'], 0, strrpos($config['OPNsense']['Suricata']['global']['snortcustomurl'], '/') + 1));
    $snort_filename = trim(substr($config['OPNsense']['Suricata']['global']['snortcustomurl'], strrpos($config['OPNsense']['Suricata']['global']['snortcustomurl'], '/') + 1));
    $snort_filename_md5 = "{$snort_filename}.md5";
}
else {
    $snort_filename_md5 = "{$snort_filename}.md5";
    $snort_rule_url = VRT_DNLD_URL;
}

/* Snort GPLv2 Community Rules filenames and URL */
if ($config['OPNsense']['Suricata']['global']['enablegplv2customurl'] == '1') {
    $snort_community_rules_filename = trim(substr($config['OPNsense']['Suricata']['global']['gplv2customurl'], strrpos($config['OPNsense']['Suricata']['global']['gplv2customurl'], '/') + 1));
    $snort_community_rules_filename_md5 = $snort_community_rules_filename . ".md5";
    $snort_community_rules_url = trim(substr($config['OPNsense']['Suricata']['global']['gplv2customurl'], 0, strrpos($config['OPNsense']['Suricata']['global']['gplv2customurl'], '/') + 1));
}
else {
    $snort_community_rules_filename = GPLV2_DNLD_FILENAME;
    $snort_community_rules_filename_md5 = GPLV2_DNLD_FILENAME . ".md5";
    $snort_community_rules_url = GPLV2_DNLD_URL;
}

/* Set up ABUSE.ch Feodo Tracker and SSL Blacklist rules filenames and URLs */
if ($config['OPNsense']['Suricata']['global']['enablefeodobotnetc2rules'] == '1') {
    $feodotracker_rules_filename = FEODO_TRACKER_DNLD_FILENAME;
    $feodotracker_rules_filename_md5 = FEODO_TRACKER_DNLD_FILENAME . ".md5";
    $feodotracker_rules_url = FEODO_TRACKER_DNLD_URL;
}
if ($config['OPNsense']['Suricata']['global']['enableabusesslblacklistrules'] == '1') {
    $sslbl_rules_filename = ABUSE_SSLBL_DNLD_FILENAME;
    $sslbl_rules_filename_md5 = ABUSE_SSLBL_DNLD_FILENAME . ".md5";
    $sslbl_rules_url = ABUSE_SSLBL_DNLD_URL;
}

/* Set up Emerging Threats rules filenames and URL */
if ($etpro == '1') {
    $et_name = "Emerging Threats Pro";
    if ($config['OPNsense']['Suricata']['global']['enableetprocustomurl'] == '1') {
        $emergingthreats_url = trim(substr($config['OPNsense']['Suricata']['global']['etprocustomruleurl'], 0, strrpos($config['OPNsense']['Suricata']['global']['etprocustomruleurl'], '/') + 1));
        $emergingthreats_filename = trim(substr($config['OPNsense']['Suricata']['global']['etprocustomruleurl'], strrpos($config['OPNsense']['Suricata']['global']['etprocustomruleurl'], '/') + 1));
        $emergingthreats_filename_md5 = $emergingthreats_filename . ".md5";
        $et_md5_remove = ET_DNLD_FILENAME . ".md5";
    } else {
        $emergingthreats_filename = ETPRO_DNLD_FILENAME;
        $emergingthreats_filename_md5 = ETPRO_DNLD_FILENAME . ".md5";
        $emergingthreats_url = ETPRO_BASE_DNLD_URL;
        $emergingthreats_url .= "{$etproid}/suricata-{$suri_eng_ver}/";
        $et_md5_remove = ET_DNLD_FILENAME . ".md5";
    }
    unlink_if_exists("{$suricatadir}{$et_md5_remove}");
} else {
    $et_name = "Emerging Threats Open";
    if ($config['OPNsense']['Suricata']['global']['enableetopencustomurl'] == '1') {
        $emergingthreats_url = trim(substr($config['OPNsense']['Suricata']['global']['etopencustomruleurl'], 0, strrpos($config['OPNsense']['Suricata']['global']['etopencustomruleurl'], '/') + 1));
        $emergingthreats_filename = trim(substr($config['OPNsense']['Suricata']['global']['etopencustomruleurl'], strrpos($config['OPNsense']['Suricata']['global']['etopencustomruleurl'], '/') + 1));
        $emergingthreats_filename_md5 = $emergingthreats_filename . ".md5";
        $et_md5_remove = ETPRO_DNLD_FILENAME . ".md5";
    } else {
        $emergingthreats_filename = ET_DNLD_FILENAME;
        $emergingthreats_filename_md5 = ET_DNLD_FILENAME . ".md5";
        $emergingthreats_url = ET_BASE_DNLD_URL;
        // If using Snort rules with ET, then we should use the open-nogpl ET rules
        $emergingthreats_url .= $vrt_enabled == '1' ? "open-nogpl/" : "open/";
        $emergingthreats_url .= "suricata-{$suri_eng_ver}/";
        $et_md5_remove = ETPRO_DNLD_FILENAME . ".md5";
    }
    unlink_if_exists("{$suricatadir}{$et_md5_remove}");
}

// Set a common flag for all Emerging Threats rules (open and pro).
if ($etpro == '1' || $eto == '1')
    $emergingthreats = '1';
else
    $emergingthreats = '0';


function suricata_update_status($msg) {
    syslog(LOG_NOTICE, '[Suricata] '.$msg);
}


function suricata_download_file_url($url, $file_out) {

    /************************************************/
    /* This function downloads the file specified   */
    /* by $url using the CURL library functions and */
    /* saves the content to the file specified by   */
    /* $file.                                       */
    /*                                              */
    /* This is needed so console output can be      */
    /* suppressed to prevent XMLRPC sync errors.    */
    /*                                              */
    /* It provides logging of returned CURL errors. */
    /************************************************/

    global $config, $last_curl_error, $fout, $ch;

    $rfc2616 = array(
            100 => "100 Continue",
            101 => "101 Switching Protocols",
            200 => "200 OK",
            201 => "201 Created",
            202 => "202 Accepted",
            203 => "203 Non-Authoritative Information",
            204 => "204 No Content",
            205 => "205 Reset Content",
            206 => "206 Partial Content",
            300 => "300 Multiple Choices",
            301 => "301 Moved Permanently",
            302 => "302 Found",
            303 => "303 See Other",
            304 => "304 Not Modified",
            305 => "305 Use Proxy",
            306 => "306 (Unused)",
            307 => "307 Temporary Redirect",
            400 => "400 Bad Request",
            401 => "401 Unauthorized",
            402 => "402 Payment Required",
            403 => "403 Forbidden",
            404 => "404 Not Found",
            405 => "405 Method Not Allowed",
            406 => "406 Not Acceptable",
            407 => "407 Proxy Authentication Required",
            408 => "408 Request Timeout",
            409 => "409 Conflict",
            410 => "410 Gone",
            411 => "411 Length Required",
            412 => "412 Precondition Failed",
            413 => "413 Request Entity Too Large",
            414 => "414 Request-URI Too Long",
            415 => "415 Unsupported Media Type",
            416 => "416 Requested Range Not Satisfiable",
            417 => "417 Expectation Failed",
            500 => "500 Internal Server Error",
            501 => "501 Not Implemented",
            502 => "502 Bad Gateway",
            503 => "503 Service Unavailable",
            504 => "504 Gateway Timeout",
            505 => "505 HTTP Version Not Supported"
        );

    $last_curl_error = "";

    $fout = fopen($file_out, "wb");
    if ($fout) {
        $ch = curl_init($url);
        if (!$ch)
            return false;
        curl_setopt($ch, CURLOPT_FILE, $fout);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, "TLSv1.2, TLSv1");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);

        // detect broken connection so it disconnects after +-10 minutes (with default TCP_KEEPIDLE and TCP_KEEPINTVL) to avoid waiting forever.
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);

        // Honor any system restrictions on sending USERAGENT info
        if (!isset($config['system']['do_not_send_host_uuid'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, product::getInstance()->name() . '/' . product::getInstance()->version() . ' : ' . 'DynFi Firewall');
        }
        else {
            curl_setopt($ch, CURLOPT_USERAGENT, product::getInstance()->name() . '/' . product::getInstance()->version());
        }

        // Use the system proxy server setttings if configured
        /*if (!empty($config['system']['proxyurl'])) { TODO
            curl_setopt($ch, CURLOPT_PROXY, $config['system']['proxyurl']);
            if (!empty($config['system']['proxyport'])) {
                curl_setopt($ch, CURLOPT_PROXYPORT, $config['system']['proxyport']);
            }
            if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
                @curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY | CURLAUTH_ANYSAFE);
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$config['system']['proxyuser']}:{$config['system']['proxypass']}");
            }
        }*/

        $counter = 0;
        $rc = true;
        // Try up to 4 times to download the file before giving up
        while ($counter < 4) {
            $counter++;
            $rc = curl_exec($ch);
            if ($rc === true)
                break;
            syslog(LOG_ERR, gettext("[Suricata] ERROR: Rules download error: " . curl_error($ch)));
            syslog(LOG_NOTICE, gettext("[Suricata] Will retry the download in 15 seconds..."));
            sleep(15);
        }
        if ($rc === false)
            $last_curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (isset($rfc2616[$http_code]))
            $last_curl_error = $rfc2616[$http_code];
        curl_close($ch);
        fclose($fout);

        // If we had to try more than once, log it
        if ($counter > 1)
            syslog(LOG_NOTICE, gettext("File '" . basename($file_out) . "' download attempts: {$counter} ..."));
        return ($http_code == 200) ? true : $http_code;
    }
    else {
        $last_curl_error = gettext("Failed to create file " . $file_out);
        syslog(LOG_ERR, gettext("[Suricata] ERROR: Failed to create file {$file_out} ..."));
        return false;
    }
}


function suricata_check_rule_md5($file_url, $file_dst, $desc = "") {

    /**********************************************************/
    /* This function attempts to download the passed MD5 hash */
    /* file and compare its contents to the currently stored  */
    /* hash file to see if a new rules file has been posted.  */
    /*                                                        */
    /* On Entry: $file_url = URL for md5 hash file            */
    /*           $file_dst = Temp destination to store the    */
    /*                       downloaded hash file             */
    /*           $desc     = Short text string used to label  */
    /*                       log messages with rules type     */
    /*                                                        */
    /*  Returns: TRUE if new rule file download required.     */
    /*           FALSE if rule download not required or an    */
    /*           error occurred.                              */
    /**********************************************************/

    global $last_curl_error, $update_errors, $notify_message;
    $suricatadir = SURICATADIR;
    $filename_md5 = basename($file_dst);

    suricata_update_status(gettext("Downloading {$desc} md5 file..."));
    error_log(gettext("\tDownloading {$desc} md5 file...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
    $rc = suricata_download_file_url($file_url, $file_dst);

    // See if download from URL was successful
    if ($rc === true) {
        suricata_update_status(gettext(" done.") . "\n");
        error_log("\tChecking {$desc} md5 file...\n", 3, SURICATA_RULES_UPD_LOGFILE);
        // check md5 hash in new file against current file to see if new download is posted
        if (file_exists("{$suricatadir}{$filename_md5}")) {
            $md5_check_new = trim(file_get_contents($file_dst));
            $md5_check_old = trim(file_get_contents("{$suricatadir}{$filename_md5}"));
            if ($md5_check_new == $md5_check_old) {
                suricata_update_status(gettext("{$desc} are up to date.") . "\n");
                syslog(LOG_NOTICE, gettext("[Suricata] {$desc} are up to date..."));
                error_log(gettext("\t{$desc} are up to date.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
                $notify_message .= gettext("- {$desc} are up to date.\n");
                return false;
            }
            else
                return true;
        }
        return true;
    }
    else {
        error_log(gettext("\t{$desc} md5 download failed.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        $suricata_err_msg = gettext("Server returned error code {$rc}.");
        suricata_update_status(gettext("{$desc} md5 error ... Server returned error code {$rc}") . "\n");
        suricata_update_status(gettext("{$desc} will not be updated.") . "\n");
        syslog(LOG_ERR, gettext("[Suricata] ERROR: {$desc} md5 download failed..."));
        syslog(LOG_ERR, gettext("[Suricata] ERROR: Remote server returned error code {$rc}..."));
        error_log(gettext("\t{$suricata_err_msg}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        error_log(gettext("\tServer error message was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        error_log(gettext("\t{$desc} will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        $notify_message .= gettext("- {$desc} will not be updated, md5 download failed!\n");
        $update_errors = true;
        return false;
    }
}


function suricata_fetch_new_rules($file_url, $file_dst, $file_md5, $desc = "") {

    /**********************************************************/
    /* This function downloads the passed rules file and      */
    /* compares its computed md5 hash to the passed md5 hash  */
    /* to verify the file's integrity.                        */
    /*                                                        */
    /* On Entry: $file_url = URL of rules file                */
    /*           $file_dst = Temp destination to store the    */
    /*                       downloaded rules file            */
    /*           $file_md5 = Expected md5 hash for the new    */
    /*                       downloaded rules file            */
    /*           $desc     = Short text string for use in     */
    /*                       log messages                     */
    /*                                                        */
    /*  Returns: TRUE if download was successful.             */
    /*           FALSE if download was not successful.        */
    /**********************************************************/

    global $last_curl_error, $update_errors, $notify_message;

    $suricatadir = SURICATADIR;
    $filename = basename($file_dst);

    suricata_update_status(gettext("There is a new set of {$desc} posted. Downloading..."));
    syslog(LOG_NOTICE, gettext("[Suricata] There is a new set of {$desc} posted. Downloading {$filename}..."));
    error_log(gettext("\tThere is a new set of {$desc} posted.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
    error_log(gettext("\tDownloading file '{$filename}'...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        $rc = suricata_download_file_url($file_url, $file_dst);

    // See if the download from the URL was successful
    if ($rc === true) {
        suricata_update_status(gettext(" done.") . "\n");
        syslog(LOG_NOTICE, "[Suricata] {$desc} file update downloaded successfully.");
        error_log(gettext("\tDone downloading rules file.\n"),3, SURICATA_RULES_UPD_LOGFILE);

        // Test integrity of the rules file.  Turn off update if file has wrong md5 hash
        if ($file_md5 != trim(md5_file($file_dst))){
            suricata_update_status(gettext("{$desc} file MD5 checksum failed!") . "\n");
            syslog(LOG_ERR, gettext("[Suricata] ERROR: {$desc} file download failed.  Bad MD5 checksum."));
                    syslog(LOG_ERR, gettext("[Suricata] ERROR: Downloaded file has MD5: " . md5_file($file_dst)). gettext(", but expected MD5: {$file_md5}"));
            error_log(gettext("\t{$desc} file download failed.  Bad MD5 checksum.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            error_log(gettext("\tDownloaded {$desc} file MD5: " . md5_file($file_dst) . "\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            error_log(gettext("\tExpected {$desc} file MD5: {$file_md5}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            error_log(gettext("\t{$desc} file download failed.  {$desc} will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            $notify_message .= gettext("- {$desc} will not be updated, bad MD5 checksum.\n");
            $update_errors = true;
            return false;
        }
        $notify_message .= gettext("- {$desc} rules were updated.\n");
        return true;
    }
    else {
        suricata_update_status(gettext("{$desc} file download failed!") . "\n");
        syslog(LOG_ERR, gettext("[Suricata] ERROR: {$desc} file download failed... server returned error '{$rc}'."));
        error_log(gettext("\tERROR: {$desc} file download failed.  Remote server returned error {$rc}.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        error_log(gettext("\t{$desc} will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        $notify_message .= gettext("- {$desc} will not be updated, rules file download failed.\n");
        $update_errors = true;
        return false;
    }
}


/* Start of main code */

/*  remove old $tmpfname files if present */
if (is_dir("{$tmpfname}"))
    rmdir_recursive("{$tmpfname}");

/*  Make sure required suricatadirs exsist */
safe_mkdir("{$suricata_rules_dir}");
safe_mkdir("{$tmpfname}");
safe_mkdir("{$suricatalogdir}");

/* See if we need to automatically clear the Update Log based on 1024K size limit */
if (file_exists(SURICATA_RULES_UPD_LOGFILE)) {
    if (1048576 < filesize(SURICATA_RULES_UPD_LOGFILE)) {
        file_put_contents(SURICATA_RULES_UPD_LOGFILE, "");
    }
}
else {
    file_put_contents(SURICATA_RULES_UPD_LOGFILE, "");
}

/* Sleep for random number of seconds between 0 and 35 to spread load on rules site */
sleep(random_int(0, 35));

/* Log start time for this rules update */
error_log(gettext("Starting rules update...  Time: " . date("Y-m-d H:i:s") . "\n"), 3, SURICATA_RULES_UPD_LOGFILE);
$notify_message = gettext("Suricata rules update started: " . date("Y-m-d H:i:s") . "\n");
$notify_new_message = '';
$last_curl_error = "";
$update_errors = false;

$suricataconfigs = suricata_get_configs();

/* Save current state (running/not running) for each enabled Suricatat interface */
$active_interfaces = array();
foreach ($suricataconfigs as $value) {
    $if_real = get_real_interface($value['iface']);

    /* Skip processing for instances whose underlying physical        */
    /* interface has been removed in pfSense.                         */
    if ($if_real == "") {
        continue;
    }

    if ($value['enable'] = "1" && suricata_is_running($if_real)) {
        $active_interfaces[] = $value['iface'];
    }
}

/*  Check for and download any new Emerging Threats Rules sigs */
if ($emergingthreats == '1') {
    if (suricata_check_rule_md5("{$emergingthreats_url}{$emergingthreats_filename_md5}", "{$tmpfname}/{$emergingthreats_filename_md5}", "{$et_name} rules")) {
        /* download Emerging Threats rules file */
        $file_md5 = trim(file_get_contents("{$tmpfname}/{$emergingthreats_filename_md5}"));
        if (!suricata_fetch_new_rules("{$emergingthreats_url}{$emergingthreats_filename}", "{$tmpfname}/{$emergingthreats_filename}", $file_md5, "{$et_name} rules")) {
            $emergingthreats = '0';
        }
    } else {
        $emergingthreats = '0';
    }
}

/*  Check for and download any new Snort rule sigs */
if ($snortdownload == '1') {
    $snort_custom_url = $config['OPNsense']['Suricata']['global']['enablesnortcustomurl'] == '1' ? TRUE : FALSE;
    if (empty($snort_filename)) {
        syslog(LOG_WARNING, gettext("WARNING: No snortrules-snapshot filename has been set on Snort pkg GLOBAL SETTINGS tab.  Snort rules cannot be updated."));
        error_log(gettext("\tWARNING-- No snortrules-snapshot filename set on GLOBAL SETTINGS tab. Snort rules cannot be updated!\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        $snortdownload = '0';
    }
    elseif (suricata_check_rule_md5("{$snort_rule_url}{$snort_filename_md5}" . ($snort_custom_url ? "" : "?oinkcode={$oinkid}"), "{$tmpfname}/{$snort_filename_md5}", "Snort VRT rules")) {
        /* download snortrules file */
        $file_md5 = trim(file_get_contents("{$tmpfname}/{$snort_filename_md5}"));
        if (!suricata_fetch_new_rules("{$snort_rule_url}{$snort_filename}" . ($snort_custom_url ? "" : "?oinkcode={$oinkid}"), "{$tmpfname}/{$snort_filename}", $file_md5, "Snort rules")) {
            $snortdownload = '0';
        }
    } else {
        $snortdownload = '0';
    }
}

/*  Check for and download any new Snort GPLv2 Community Rules sigs */
if ($snortcommunityrules == '1') {
    if (suricata_check_rule_md5("{$snort_community_rules_url}{$snort_community_rules_filename_md5}", "{$tmpfname}/{$snort_community_rules_filename_md5}", "Snort GPLv2 Community Rules")) {
        /* download Snort GPLv2 Community Rules file */
        $file_md5 = trim(file_get_contents("{$tmpfname}/{$snort_community_rules_filename_md5}"));
        if (!suricata_fetch_new_rules("{$snort_community_rules_url}{$snort_community_rules_filename}", "{$tmpfname}/{$snort_community_rules_filename}", $file_md5, "Snort GPLv2 Community Rules")) {
            $snortcommunityrules = '0';
        }
    } else {
        $snortcommunityrules = '0';
    }
}

/*  Download any new ABUSE.ch Fedoo Tracker Rules sigs */
if ($feodotracker_rules == '1') {
    // Grab the MD5 hash of our last successful download if available
    if (file_exists("{$suricatadir}{$feodotracker_rules_filename}.md5")) {
        $old_file_md5 = trim(file_get_contents("{$suricatadir}{$feodotracker_rules_filename}.md5"));
    }
    else {
        $old_file_md5 = "0";
    }

    suricata_update_status(gettext("Downloading Feodo Tracker Botnet C2 IP rules file..."));
    error_log(gettext("\tDownloading Feodo Tracker Botnet C2 IP rules file...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
    $rc = suricata_download_file_url("{$feodotracker_rules_url}{$feodotracker_rules_filename}", "{$tmpfname}/{$feodotracker_rules_filename}");

    // See if the download from the URL was successful
    if ($rc === true) {
        suricata_update_status(gettext(" done.") . "\n");
        syslog(LOG_NOTICE, "[Suricata] Feodo Tracker Botnet C2 IP rules file update downloaded successfully.");
        error_log(gettext("\tDone downloading rules file.\n"),3, SURICATA_RULES_UPD_LOGFILE);

        // See if file has changed from our previously downloaded version
        if ($old_file_md5 == trim(md5_file("{$tmpfname}/{$feodotracker_rules_filename}"))) {
            // File is unchanged from previous download, so no update required
            suricata_update_status(gettext("Feodo Tracker Botnet C2 IP rules are up to date.") . "\n");
            syslog(LOG_NOTICE, gettext("[Suricata] Feodo Tracker Botnet C2 IP rules are up to date..."));
            error_log(gettext("\tFeodo Tracker Botnet C2 IP rules are up to date.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            $notify_message .= gettext("- Feodo Tracker Botnet C2 IP rules are up to date.\n");
            $feodotracker_rules = '0';
        }
        else {
            // Downloaded file is changed, so update our local MD5 hash and extract the new rules
            file_put_contents("{$suricatadir}{$feodotracker_rules_filename}.md5", trim(md5_file("{$tmpfname}/{$feodotracker_rules_filename}")));
            suricata_update_status(gettext("Installing Feodo Tracker Botnet C2 IP rules..."));
            error_log(gettext("\tExtracting and installing Feodo Tracker Botnet C2 IP rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            exec("/usr/bin/tar xzf {$tmpfname}/{$feodotracker_rules_filename} -C {$suricata_rules_dir}");
            suricata_update_status(gettext("Feodo Tracker Botnet C2 IP rules were updated.") . "\n");
            syslog(LOG_NOTICE, gettext("[Suricata] Feodo Tracker Botnet C2 IP rules were updated..."));
            error_log(gettext("\tFeodo Tracker Botnet C2 IP rules were updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            $notify_message .= gettext("- Feodo Tracker Botnet C2 IP rules were updated.\n");
        }
    }
    else {
        suricata_update_status(gettext("Feodo Tracker Botnet C2 IP rules file download failed!") . "\n");
        syslog(LOG_ERR, gettext("[Suricata] ERROR: Feodo Tracker Botnet C2 IP rules file download failed... server returned error '{$rc}'."));
        error_log(gettext("\tERROR: Feodo Tracker Botnet C2 IP rules file download failed.  Remote server returned error {$rc}.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        error_log(gettext("\tFeodo Tracker Botnet C2 IP rules will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        $notify_message .= gettext("- Feodo Tracker Botnet C2 IP rules will not be updated, rules file download failed!\n");
        $update_errors = true;
        $feodotracker_rules = '0';
    }
}

/*  Download any new ABUSE.ch SSL Blacklist Rules sigs */
if ($sslbl_rules == '1') {
    // Grab the MD5 hash of our last successful download if available
    if (file_exists("{$suricatadir}{$sslbl_rules_filename}.md5")) {
        $old_file_md5 = trim(file_get_contents("{$suricatadir}{$sslbl_rules_filename}.md5"));
    }
    else {
        $old_file_md5 = "0";
    }

    suricata_update_status(gettext("Downloading ABUSE.ch SSL Blacklist rules file..."));
    error_log(gettext("\tDownloading ABUSE.ch SSL Blacklist rules file...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
    $rc = suricata_download_file_url("{$sslbl_rules_url}{$sslbl_rules_filename}", "{$tmpfname}/{$sslbl_rules_filename}");

    // See if the download from the URL was successful
    if ($rc === true) {
        suricata_update_status(gettext(" done.") . "\n");
        syslog(LOG_NOTICE, "[Suricata] ABUSE.ch SSL Blacklist rules file update downloaded successfully.");
        error_log(gettext("\tDone downloading rules file.\n"),3, SURICATA_RULES_UPD_LOGFILE);

        // See if file has changed from our previously downloaded version
        if ($old_file_md5 == trim(md5_file("{$tmpfname}/{$sslbl_rules_filename}"))) {
            // File is unchanged from previous download, so no update required
            suricata_update_status(gettext("ABUSE.ch SSL Blacklist rules are up to date.") . "\n");
            syslog(LOG_NOTICE, gettext("[Suricata] ABUSE.ch SSL Blacklist rules are up to date..."));
            error_log(gettext("\tABUSE.ch SSL Blacklist rules are up to date.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            $notify_message .= gettext("- ABUSE.ch SSL Blacklist rules are up to date.\n");
            $sslbl_rules = '0';
        }
        else {
            // Downloaded file is changed, so update our local MD5 hash and extract the new rules
            file_put_contents("{$suricatadir}{$sslbl_rules_filename}.md5", trim(md5_file("{$tmpfname}/{$sslbl_rules_filename}")));
            suricata_update_status(gettext("Installing ABUSE.ch SSL Blacklist rules..."));
            error_log(gettext("\tExtracting and installing ABUSE.ch SSL Blacklist rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            exec("/usr/bin/tar xzf {$tmpfname}/{$sslbl_rules_filename} -C {$suricata_rules_dir}");
            suricata_update_status(gettext("ABUSE.ch SSL Blacklist rules were updated.") . "\n");
            syslog(LOG_NOTICE, gettext("[Suricata] ABUSE.ch SSL Blacklist rules were updated..."));
            error_log(gettext("\tABUSE.ch SSL Blacklist rules were updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            $notify_message .= gettext("- ABUSE.ch SSL Blacklist rules were updated.\n");
        }
    }
    else {
        suricata_update_status(gettext("ABUSE.ch SSL Blacklist rules file download failed!") . "\n");
        syslog(LOG_ERR, gettext("[Suricata] ERROR: ABUSE.ch SSL Blacklist rules file download failed... server returned error '{$rc}'."));
        error_log(gettext("\tERROR: ABUSE.ch SSL Blacklist rules file download failed.  Remote server returned error {$rc}.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        error_log(gettext("\tABUSE.ch SSL Blacklist rules will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        $notify_message .= gettext("- ABUSE.ch SSL Blacklist rules will not be updated, file download failed!\n");
        $update_errors = true;
        $sslbl_rules = '0';
    }
}

/*  Download any new Extra Rules */
if (($enable_extra_rules == '1') && !empty($extra_rules)) {
    $extraupdated = '0';
    safe_mkdir("{$tmpfname}/extra");
    $tmpextradir = "{$tmpfname}/extra";
    $existing_extra_rules = array();
    foreach ($extra_rules as $exrule) {
        $format = (substr($exrule['url'], strrpos($exrule['url'], 'rules')) == 'rules') ? ".rules" : ".tar.gz";
        $rulesfilename = EXTRARULE_FILE_PREFIX . $exrule['name'] . $format;
        if (file_exists("{$suricatadir}{$rulesfilename}.md5")) {
            $old_file_md5 = trim(file_get_contents("{$suricatadir}{$rulesfilename}.md5"));
        } else {
            $old_file_md5 = "0";
        }

        if (($exrule['md5'] == '1') &&
            !suricata_check_rule_md5($exrule['url'] . '.md5', "{$tmpextradir}/{$rulesfilename}.md5", "Extra {$exrule['name']} rules")) {

            continue;
        }

        suricata_update_status(gettext("Downloading Extra {$exrule['name']} rules file..."));
        error_log(gettext("\tDownloading Extra {$exrule['name']} rules file...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        $rc = suricata_download_file_url($exrule['url'], "{$tmpextradir}/{$rulesfilename}");

        // See if the download from the URL was successful
        if ($rc === true) {
            suricata_update_status(gettext(" done.") . "\n");
            syslog(LOG_NOTICE, "[Suricata] Extra {$exrule['name']} rules file update downloaded successfully.");
            error_log(gettext("\tDone downloading rules file.\n"),3, SURICATA_RULES_UPD_LOGFILE);

            // See if file has changed from our previously downloaded version
            if ($old_file_md5 == trim(md5_file("{$tmpextradir}/{$rulesfilename}"))) {
                // File is unchanged from previous download, so no update required
                suricata_update_status(gettext("Extra {$exrule['name']} rules are up to date.") . "\n");
                syslog(LOG_NOTICE, gettext("[Suricata] Extra {$exrule['name']} rules are up to date..."));
                error_log(gettext("\tExtra {$exrule['name']} rules are up to date.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
                $notify_message .= gettext("- Extra {$exrule['name']} rules are up to date.\n");
            } else {
                file_put_contents("{$suricatadir}{$rulesfilename}.md5", trim(md5_file("{$tmpextradir}/{$rulesfilename}")));
                suricata_update_status(gettext("Installing Extra {$exrule['name']} rules..."));
                error_log(gettext("\tExtracting and installing {$exrule['name']} IP rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
                if ($format == '.rules') {
                    @copy("{$tmpextradir}/{$rulesfilename}", "{$suricata_rules_dir}{$rulesfilename}");
                } else {
                    safe_mkdir("{$tmpextradir}/{$exrule['name']}");
                    exec("/usr/bin/tar xzf {$tmpextradir}/{$rulesfilename} -C {$tmpextradir}/{$exrule['name']}/");
                    unlink_if_exists("{$suricata_rules_dir}" . EXTRARULE_FILE_PREFIX . $exrule['name'] . "-*.rules");
                    $downloaded_rules = array();
                    $files = suricata_listfiles("{$tmpextradir}/{$exrule['name']}");
                    foreach ($files as $file) {
                        $newfile = basename($file);
                        $downloaded_rules[] = $newfile;
                        if (substr($newfile, -6) == ".rules") {
                            @copy($file, $suricata_rules_dir . EXTRARULE_FILE_PREFIX . $exrule['name'] . "-" . $newfile);
                        }
                    }
                    if (file_exists("{$suricatadir}{$rulesfilename}.ruleslist")) {
                        $existing_rules = unserialize(file_get_contents("{$suricatadir}{$rulesfilename}.ruleslist"));
                        $newrules = array_diff($downloaded_rules, $existing_rules);
                        if (!empty($newrules)) {
                            $notify_new_message .= gettext("- Extra {$exrule['name']} rules: " . implode(', ', $newrules) . "\n");
                            @file_put_contents("{$suricatadir}{$rulesfilename}.ruleslist", serialize($downloaded_rules));
                        }
                    } else {
                        @file_put_contents("{$suricatadir}{$rulesfilename}.ruleslist", serialize($downloaded_rules));
                    }
                }

                suricata_update_status(gettext("Extra {$exrule['name']} rules were updated.") . "\n");
                syslog(LOG_NOTICE, gettext("[Suricata] Extra {$exrule['name']} rules were updated..."));
                error_log(gettext("\tExtra {$exrule['name']} rules were updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
                $notify_message .= gettext("- Extra {$exrule['name']} rules were updated.\n");
                $extraupdated = '1';
            }
        } else {
            suricata_update_status(gettext("Extra {$exrule['name']} rules file download failed!") . "\n");
            syslog(LOG_ERR, gettext("[Suricata] ERROR: Extra {$exrule['name']} rules file download failed... server returned error '{$rc}'."));
            error_log(gettext("\tERROR: Extra {$exrule['name']} rules file download failed. Remote server returned error {$rc}.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            error_log(gettext("\tExtra {$exrule['name']} rules will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
            $notify_message .= gettext("- Extra {$exrule['name']} rules will not be updated, file download failed!\n");
            $update_errors = true;
        }
        $existing_extra_rules[] = $exrule['name'];
    }
    rmdir_recursive($tmpextradir);
}

/* Untar Emerging Threats rules file to tmp if downloaded */
if ($emergingthreats == '1') {
    safe_mkdir("{$tmpfname}/emerging");
    if (file_exists("{$tmpfname}/{$emergingthreats_filename}")) {
        suricata_update_status(gettext("Installing {$et_name} rules..."));
        error_log(gettext("\tExtracting and installing {$et_name} rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        exec("/usr/bin/tar xzf {$tmpfname}/{$emergingthreats_filename} -C {$tmpfname}/emerging rules/");

        /* Remove the old Emerging Threats rules files */
        $eto_prefix = ET_OPEN_FILE_PREFIX;
        $etpro_prefix = ET_PRO_FILE_PREFIX;
        unlink_if_exists("{$suricata_rules_dir}{$eto_prefix}*.rules");
        unlink_if_exists("{$suricata_rules_dir}{$etpro_prefix}*.rules");
        unlink_if_exists("{$suricata_rules_dir}{$eto_prefix}*ips.txt");
        unlink_if_exists("{$suricata_rules_dir}{$etpro_prefix}*ips.txt");

        // The code below renames ET files with a prefix, so we
        // skip renaming the Suricata default events rule files
        // that are also bundled in the ET rules.
        $default_rules = array( "decoder-events.rules", "dns-events.rules", "files.rules", "http-events.rules", "smtp-events.rules", "stream-events.rules", "tls-events.rules" );
        $files = glob("{$tmpfname}/emerging/rules/*.rules");
        $downloaded_rules = array();
        // Determine the correct prefix to use based on which
        // Emerging Threats rules package is enabled.
        if ($etpro == '1')
            $prefix = ET_PRO_FILE_PREFIX;
        else
            $prefix = ET_OPEN_FILE_PREFIX;
        foreach ($files as $file) {
            $newfile = basename($file);
            $downloaded_rules[] = $newfile;
            if (in_array($newfile, $default_rules))
                @copy($file, "{$suricata_rules_dir}{$newfile}");
            else {
                if (strpos($newfile, $prefix) === FALSE)
                    @copy($file, "{$suricata_rules_dir}{$prefix}{$newfile}");
                else
                    @copy($file, "{$suricata_rules_dir}{$newfile}");
            }
        }
        /* IP lists for Emerging Threats rules */
        $files = glob("{$tmpfname}/emerging/rules/*ips.txt");
        foreach ($files as $file) {
            $newfile = basename($file);
            if ($etpro == '1')
                @copy($file, "{$suricata_rules_dir}" . ET_PRO_FILE_PREFIX . "{$newfile}");
            else
                @copy($file, "{$suricata_rules_dir}" . ET_OPEN_FILE_PREFIX . "{$newfile}");
        }
                /* base etc files for Emerging Threats rules */
        foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
            if (file_exists("{$tmpfname}/emerging/rules/{$file}"))
                @copy("{$tmpfname}/emerging/rules/{$file}", "{$tmpfname}/ET_{$file}");
        }

        /*  Copy emergingthreats md5 sig to Suricata dir */
        if (file_exists("{$tmpfname}/{$emergingthreats_filename_md5}")) {
            @copy("{$tmpfname}/{$emergingthreats_filename_md5}", "{$suricatadir}{$emergingthreats_filename_md5}");
        }
        if (file_exists("{$suricatadir}{$emergingthreats_filename}.ruleslist")) {
            $existing_rules = unserialize(file_get_contents("{$suricatadir}{$emergingthreats_filename}.ruleslist"));
            $newrules = array_diff($downloaded_rules, $existing_rules);
            if (!empty($newrules)) {
                $notify_new_message .= gettext("- {$et_name} rules: " . implode(', ', $newrules) . "\n");
                @file_put_contents("{$suricatadir}{$emergingthreats_filename}.ruleslist", serialize($downloaded_rules));
            }
        } else {
            @file_put_contents("{$suricatadir}{$emergingthreats_filename}.ruleslist", serialize($downloaded_rules));
        }
        suricata_update_status(gettext(" done.") . "\n");
        error_log(gettext("\tInstallation of {$et_name} rules completed.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        rmdir_recursive("{$tmpfname}/emerging");
    }
}

/* Untar Snort rules file to tmp */
if ($snortdownload == '1') {
    if (file_exists("{$tmpfname}/{$snort_filename}")) {
        /* Remove the old Snort rules files */
        $vrt_prefix = VRT_FILE_PREFIX;
        unlink_if_exists("{$suricata_rules_dir}{$vrt_prefix}*.rules");
        suricata_update_status(gettext("Installing Snort rules..."));
        error_log(gettext("\tExtracting and installing Snort rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);

        /* extract snort.org rules and add prefix to all snort.org files */
        safe_mkdir("{$tmpfname}/snortrules");
        exec("/usr/bin/tar xzf {$tmpfname}/{$snort_filename} -C {$tmpfname}/snortrules rules/");
        $files = glob("{$tmpfname}/snortrules/rules/*.rules");
        $downloaded_rules = array();
        foreach ($files as $file) {
            $newfile = basename($file);
            $downloaded_rules[] = $file;
            @copy($file, "{$suricata_rules_dir}" . VRT_FILE_PREFIX . "{$newfile}");
        }

        /* IP lists */
        $files = glob("{$tmpfname}/snortrules/rules/*.txt");
        foreach ($files as $file) {
            $newfile = basename($file);
            @copy($file, "{$suricata_rules_dir}{$newfile}");
        }
        rmdir_recursive("{$tmpfname}/snortrules");

        /* extract base etc files */
        exec("/usr/bin/tar xzf {$tmpfname}/{$snort_filename} -C {$tmpfname} etc/");
        foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
            if (file_exists("{$tmpfname}/etc/{$file}"))
                @copy("{$tmpfname}/etc/{$file}", "{$tmpfname}/VRT_{$file}");
        }
        rmdir_recursive("{$tmpfname}/etc");
        if (file_exists("{$tmpfname}/{$snort_filename_md5}")) {
            @copy("{$tmpfname}/{$snort_filename_md5}", "{$suricatadir}{$snort_filename_md5}");
        }
        if (file_exists("{$suricatadir}{$snort_filename}.ruleslist")) {
            $existing_rules = unserialize(file_get_contents("{$suricatadir}{$snort_filename}.ruleslist"));
            $newrules = array_diff($downloaded_rules, $existing_rules);
            if (!empty($newrules)) {
                $notify_new_message .= gettext("- Snort rules: " . implode(', ', $newrules) . "\n");
                @file_put_contents("{$suricatadir}{$snort_filename}.ruleslist", serialize($downloaded_rules));
            }
        } else {
            @file_put_contents("{$suricatadir}{$snort_filename}.ruleslist", serialize($downloaded_rules));
        }
        suricata_update_status(gettext(" done.") . "\n");
        error_log(gettext("\tInstallation of Snort rules completed.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
    }
}

/* Untar Snort GPLv2 Community rules file to tmp */
if ($snortcommunityrules == '1') {
    safe_mkdir("{$tmpfname}/community");
    if (file_exists("{$tmpfname}/{$snort_community_rules_filename}")) {
        suricata_update_status(gettext("Installing Snort GPLv2 Community Rules..."));
        error_log(gettext("\tExtracting and installing Snort GPLv2 Community Rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        exec("/usr/bin/tar xzf {$tmpfname}/{$snort_community_rules_filename} -C {$tmpfname}/community/");

        $files = glob("{$tmpfname}/community/community-rules/*.rules");
        $downloaded_rules = array();
        foreach ($files as $file) {
            $newfile = basename($file);
            $downloaded_rules[] = $newfile;
            @copy($file, "{$suricata_rules_dir}" . GPL_FILE_PREFIX . "{$newfile}");
        }
                /* base etc files for Snort GPLv2 Community rules */
        foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
            if (file_exists("{$tmpfname}/community/community-rules/{$file}"))
                @copy("{$tmpfname}/community/community-rules/{$file}", "{$tmpfname}/" . GPL_FILE_PREFIX . "{$file}");
        }
        /*  Copy snort community md5 sig to suricata dir */
        if (file_exists("{$tmpfname}/{$snort_community_rules_filename_md5}")) {
            @copy("{$tmpfname}/{$snort_community_rules_filename_md5}", "{$suricatadir}{$snort_community_rules_filename_md5}");
        }
        if (file_exists("{$suricatadir}{$snort_community_rules_filename}.ruleslist")) {
            $existing_rules = unserialize(file_get_contents("{$suricatadir}{$snort_community_rules_filename}.ruleslist"));
            $newrules = array_diff($downloaded_rules, $existing_rules);
            if (!empty($newrules)) {
                $notify_new_message .= gettext("- Snort GPLv2 Community Rules: " . implode(', ', $newrules) . "\n");
                @file_put_contents("{$suricatadir}{$snort_community_rules_filename}.ruleslist", serialize($downloaded_rules));
            }
        } else {
            @file_put_contents("{$suricatadir}{$snort_community_rules_filename}.ruleslist", serialize($downloaded_rules));
        }
        suricata_update_status(gettext(" done.") . "\n");
        error_log(gettext("\tInstallation of Snort GPLv2 Community Rules completed.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
        rmdir_recursive("{$tmpfname}/community");
    }
}

// If removing deprecated rules categories, then do it
if ($config['OPNsense']['Suricata']['global']['hidedeprecatedrules'] == '1') {
    syslog(LOG_NOTICE, gettext("[Suricata] Hide Deprecated Rules is enabled.  Removing obsoleted rules categories."));
    suricata_remove_dead_rules();
}

function suricata_apply_customizations($suricatacfg, $if_real) {

    global $vrt_enabled;
    $suricatadir = SURICATADIR;

    suricata_prepare_rule_files($suricatacfg, "{$suricatadir}suricata_{$if_real}");

    /* Copy the master config and map files to the interface directory */
    @copy("{$suricatadir}classification.config", "{$suricatadir}suricata_{$if_real}/classification.config");
    @copy("{$suricatadir}reference.config", "{$suricatadir}suricata_{$if_real}/reference.config");
    @copy("{$suricatadir}gen-msg.map", "{$suricatadir}suricata_{$if_real}/gen-msg.map");
    @copy("{$suricatadir}unicode.map", "{$suricatadir}suricata_{$if_real}/unicode.map");
}

/* If we updated any rules, then refresh all the Suricata interfaces */
if ($snortdownload == '1' || $emergingthreats == '1' || $snortcommunityrules == '1' || $feodotracker_rules == '1' || $sslbl_rules == '1' || $extraupdated == '1') {

    /* If we updated Snort or ET rules, rebuild the config and map files as nescessary */
    if ($snortdownload == '1' || $emergingthreats == '1' || $snortcommunityrules == '1') {

        error_log(gettext("\tCopying new config and map files...\n"), 3, SURICATA_RULES_UPD_LOGFILE);

        /******************************************************************/
        /* Build the classification.config and reference.config files     */
        /* using the ones from all the downloaded rules plus the default  */
        /* files installed with Suricata.                                 */
        /******************************************************************/
        $cfgs = glob("{$tmpfname}/*reference.config");
        $cfgs[] = "{$suricatadir}reference.config";
        suricata_merge_reference_configs($cfgs, "{$suricatadir}reference.config");
        $cfgs = glob("{$tmpfname}/*classification.config");
        $cfgs[] = "{$suricatadir}classification.config";
        suricata_merge_classification_configs($cfgs, "{$suricatadir}classification.config");

        /* Determine which map files to use for the master copy. */
        /* The Snort VRT ones are preferred, if available.       */
        if ($snortdownload == '1')
            $prefix = "VRT_";
        elseif ($emergingthreats == '1')
            $prefix = "ET_";
        elseif ($snortcommunityrules == '1')
            $prefix = GPL_FILE_PREFIX;
        if (file_exists("{$tmpfname}/{$prefix}unicode.map"))
            @copy("{$tmpfname}/{$prefix}unicode.map", "{$suricatadir}unicode.map");
        if (file_exists("{$tmpfname}/{$prefix}gen-msg.map"))
            @copy("{$tmpfname}/{$prefix}gen-msg.map", "{$suricatadir}gen-msg.map");
    }

    /* Start the rules rebuild proccess for each configured interface */
    if (!empty($suricataconfigs)) {

        /* Create configuration for each active Suricata interface */
        foreach ($suricataconfigs as $value) {
            $if_real = get_real_interface($value['iface']);

            /* Skip processing for instances whose underlying physical       */
            /* interface has been removed in pfSense.                        */
            if ($if_real == "") {
                continue;
            }

            // Make sure the interface subdirectory exists.  We need to re-create
            // it during a pkg reinstall on the initial rules set download.
            if (!is_dir("{$suricatadir}suricata_{$if_real}"))
                safe_mkdir("{$suricatadir}suricata_{$if_real}");
            if (!is_dir("{$suricatadir}suricata_{$if_real}/rules"))
                safe_mkdir("{$suricatadir}suricata_{$if_real}/rules");
            $tmp = "Updating rules configuration for: " .$value['iface']. " ...";
            suricata_update_status(gettext($tmp));
            suricata_apply_customizations($value, $if_real);
            $tmp = "\t" . $tmp . "\n";
            error_log($tmp, 3, SURICATA_RULES_UPD_LOGFILE);
            suricata_update_status(gettext(" done.") . "\n");

            // If running, reload the rules for this interface
            if (in_array($value['iface'], $active_interfaces)) {
                // If running and "Live Reload" is enabled, just reload the configuration;
                // otherwise, start/restart the interface instance of Suricata.
                if (suricata_is_running($if_real) && $config['OPNsense']['Suricata']['global']['liveswapupdates'] == '1') {
                    syslog(LOG_NOTICE, gettext("[Suricata] Live-Reload of rules from auto-update is enabled..."));
                    error_log(gettext("\tLive-Reload of updated rules is enabled...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
                    suricata_update_status(gettext("Signaling Suricata to live-load the new set of rules for " .$value['iface']. "..."));
                    suricata_reload_config($value);
                    suricata_update_status(gettext(" done.") . "\n");
                    error_log(gettext("\tLive-Reload of updated rules requested for " . $value['iface'] . ".\n"), 3, SURICATA_RULES_UPD_LOGFILE);
                }
                else {
                    suricata_update_status(gettext("Restarting Suricata to activate the new set of rules for " . $value['iface'] . "..."));
                    error_log(gettext("\tRestarting Suricata to activate the new set of rules for " . $value['iface'] . "...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
                    suricata_stop($if_real);
                    sleep(5);
                    suricata_start($if_real);
                    suricata_update_status(gettext(" done.") . "\n");
                    syslog(LOG_NOTICE, gettext("[Suricata] Suricata has restarted with your new set of rules for " . $value['iface'] . "..."));
                    error_log(gettext("\tSuricata has restarted with your new set of rules for " . $value['iface'] . ".\n"), 3, SURICATA_RULES_UPD_LOGFILE);
                }
            }
        }
    }
    else {
        suricata_update_status(gettext("Warning:  No interfaces configured for Suricata were found!") . "\n");
        error_log(gettext("\tWarning:  No interfaces configured for Suricata were found...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
    }
}

// Remove old $tmpfname files
if (is_dir("{$tmpfname}")) {
    suricata_update_status(gettext("Cleaning up after rules extraction..."));
    rmdir_recursive("{$tmpfname}");
    suricata_update_status(gettext(" done.") . "\n");
}

suricata_update_status(gettext("The Rules update has finished.") . "\n");
syslog(LOG_NOTICE, gettext("[Suricata] The Rules update has finished."));
error_log(gettext("The Rules update has finished.  Time: " . date("Y-m-d H:i:s"). "\n\n"), 3, SURICATA_RULES_UPD_LOGFILE);
$notify_message .= gettext("Suricata rules update finished: " . date("Y-m-d H:i:s"));

/* Save this update status to the rulesupd_status file */
$status = time() . '|';
if ($update_errors) {
    $status .= gettext("failed");
}
else {
    $status .= gettext("success");
}
@file_put_contents(SURICATADIR . "rulesupd_status", $status);

/* TODO

if ($config['OPNsense']['Suricata']['global']['updatenotify'] == '1') {
    notify_all_remote($notify_message);
}
if (($config['OPNsense']['Suricata']['global']['rulecategoriesnotify'] == '1') &&
    ($notify_new_message)) {
    notify_all_remote("Suricata new rule categories are available:\n" . $notify_new_message);
}*/

?>
