<?php

/**
 * @brief print usage and exit
 */
function usage($exitCode) {
    global $argv;
    echo "Usage: " . $argv[0] . " /path/to/zray.tar.gz [ nginx=/path/to/nginx/conf.d/ ]\n";
    exit($exitCode);
}

class Installer {
    protected $extensionsDir    = "";
    protected $scanDirCli       = "";
    protected $user             = - 1;
    protected $tarfile          = "";
    protected $targetFolder     = "/opt/zray";
    protected $webServerScanDir = "";
    protected $webuser          = "www-data";
    protected $webgroup         = "www-data";
    protected $isNginx          = false;
    protected $nginxPath        = "";

    /**
     * @brief are we running on Linux?
     */
    public function isLinux() {
        $osname = PHP_OS;
        return (strcasecmp($osname, "linux") == 0);
    }

    /**
     * @brief are we running on Debia/Ubuntu?
     */
    public function isDebian() {
        exec("uname -a", $out);
        return (strpos($out[0], "Debian") !== FALSE) || (strpos($out[0], "Ubuntu") !== FALSE);
    }

    /**
     * @brief are we running on CentOS?
     */
    public function isCentOS() {
        exec("uname -a", $out);
        return strpos($out[0], "CentOS") !== FALSE;
    }

    /**
     * @brief are we running on RHEL?
     */
    public function isRHEL() {
        exec("uname -a", $out);
        return strpos($out[0], "RHEL") !== FALSE;
    }

    /**
     * @brief are we running on OSX?
     */
    public function isOSX() {
        $osname = PHP_OS;
        return (strcasecmp($osname, "darwin") == 0);
    }

    /**
     * @brief are we using apache?
     */
    public function isApache() {
        return !$this->getIsNginx();
    }

    /**
     * @brief detect and print the current OS name
     * If an unknown OS is found, print an error message
     * and exit
     */
    public function validateOS() {
        if ($this->isOSX()) {
            $this->message("OS: OSX");
        }
        else if($this->isCentOS()) {
            $this->message("OS: CentOS");
        }
        else if($this->isRHEL()) {
            $this->message("OS: RHEL");
        }
        else if($this->isDebian()) {
            $this->message("OS: Debian Linux");
        }
        else {
            $this->error("This OS is not supported");
        }
    }

    /**
     * @brief trying to fetch apache user and group dynamically
     */
    public function fetchApacheUserAndGroup()
    {
        exec('apachectl -S 2>/dev/null |grep "User:" |sed \'s%User: name="\(.*\)".*%\1%\' | grep -v "User:"', $webUser);
        exec('apachectl -S 2>/dev/null |grep "Group:" |sed \'s%Group: name="\(.*\)".*%\1%\' | grep -v "Group:"', $webGroup);
        if (!empty($webUser)) {
            $this->webuser = $webUser[0];
        }

        if (!empty($webGroup)) {
            $this->webgroup = $webGroup[0];
        }
    }

    /**
     * @brief ctor, initialize default values per OS / Distro
     */
    public function __construct($isNginx) {
        @ob_implicit_flush(true);
        @ob_end_clean();

        $this->setIsNginx($isNginx);
        $this->validateOS();
        $this->extensionsDir = ini_get("extension_dir");
        if ($this->isDebian()) {
            $this->scanDirCli = dirname(php_ini_loaded_file()). "/conf.d";

            if ($this->getIsNginx()) {
                $this->webServerScanDir = str_replace("cli", "fpm", $this->scanDirCli);
            }
            else {
                $this->webServerScanDir = str_replace("cli", "apache2", $this->scanDirCli);
            }

        }
        else {
            $this->scanDirCli       = "/etc/php.d";
            $this->webServerScanDir = "/etc/php.d";
        }

        if ($this->isApache()) {
            // APACHE
            if ($this->isDebian()) {
                $this->webuser  = 'www-data';
                $this->webgroup = 'www-data';

                $this->fetchApacheUserAndGroup();

            }
            elseif ($this->isCentOS() || $this->isRHEL()) {
                $this->webuser  = 'apache';
                $this->webgroup = 'apache';

                $this->fetchApacheUserAndGroup();
            }
            elseif ($this->isOSX()) {
                $this->webuser = "_www";
                $this->webgroup = "_www";
            }
        }
        else {
            // NGINX
            if ($this->isDebian()) {
                $this->webuser = "www-data";
                $this->webgroup = "www-data";
            }
            else if($this->isOSX()) {
                $this->webuser = "_www";
                $this->webgroup = "_www";
            }
            else if($this->isCentOS() || $this->isRHEL()) {
                $this->webuser  = "nginx";
                $this->webgroup = "nginx";
            }
        }

        $this->message("WebServer user: {$this->webuser}");
        $this->message("WebServer group: {$this->webgroup}");
        $this->getUid();

        if ($this->isOSX()) {
            $this->targetFolder = "/usr/local/opt/zray";
        }

        $this->message("Installation folder: {$this->targetFolder}");
    }

    private function getUid() {
        exec("/usr/bin/id", $output);
        preg_match("%uid=(\d+)%i", $output[0], $matches);
        $this->user = intval($matches[1]);
    }

    /**
     * @brief is this Installer object initialized properly?
     */
    public function isOk() {
        return ($this->getExtensionsDir()!= null) && ($this->getExtensionsDir()!= "");
    }

    /**
     * @return
     */
    public function getExtensionsDir() {
        return $this->extensionsDir;
    }

    /**
     * @brief return true if the current user is root
     */
    public function isRoot() {
        return ($this->user == 0);
    }

    public function isTarFileExists() {
        return file_exists($this->tarfile);
    }

    /**
     * @brief
     * @param <unknown> $path
     * @return Installer
     */
    public function setTarFile($path) {
        $this->tarfile = $path;
        return $this;
    }

    /**
     * @brief perform sanity check befor continuing with the installation
     * This function exits with different exit code per error type
     */
    public function sanity() {
        global $argv;

        if (!$this->isRoot()) {
            $this->error("You must be root for running this script!", 1);
        }

        // Check that the tar file exists
        if (!$this->isTarFileExists()) {
            $this->error("No such file: $argv[1]", 2);
        }
    }

    /**
     * @brief
     */
    public function installZRayUIConfigFile() {
        if ($this->isApache()) {
            if ($this->isDebian()) {
                $this->message("cp {$this->targetFolder}/zray-ui.conf /etc/apache2/sites-available");
                exec("cp {$this->targetFolder}/zray-ui.conf /etc/apache2/sites-available");
            }
            else if($this->isCentOS() || $this->isRHEL()) {
                $this->message("cp {$this->targetFolder}/zray-ui.conf /etc/httpd/conf.d/");
                exec("cp {$this->targetFolder}/zray-ui.conf /etc/httpd/conf.d/");
            }
            else if($this->isOSX()) {
                $this->message("ln -sf  {$this->targetFolder}/zray-ui-osx.conf /private/etc/apache2/other/zray-ui-osx.conf");
                exec("ln -sf  {$this->targetFolder}/zray-ui-osx.conf /private/etc/apache2/other/zray-ui-osx.conf");
            }
        }
        else {
            // Nginx
            if ($this->isDebian()) {
                $content = file_get_contents('/etc/nginx/sites-available/default');
                if (!preg_match('/ZendServer/m', $content)) {
                    $newContent = preg_replace("/^([^#]*server_name[^;]*;)/m", "$1\nlocation /ZendServer {try_files \$uri \$uri/ /ZendServer/index.php?\$args;}", $content);
                    file_put_contents('/etc/nginx/sites-available/default', $newContent);
                }
            }
            else if ($this->isCentOS() || $this->isRHEL()) {
                if (!file_exists('/etc/nginx/default.d/zray.conf')) {
                    file_put_contents('/etc/nginx/default.d/zray.conf', "location /ZendServer {try_files \$uri \$uri/ /ZendServer/index.php?\$args;}");
                }
            }
        }
    }

    /**
     * @brief perform the actual installation
     */
    public function install() {
        // Check that we can proceed with the installation
        $this->sanity();

        if (file_exists($this->targetFolder)) {
            $this->message("Old installation found");
            // rename it
            $backupName = "{$this->targetFolder}." . time();
            if (!rename($this->targetFolder, $backupName)) {
                $this->error("failed to backup old installation", 3);
            }

            $this->message("Old installation was renamed to " . $backupName);
        }

        if ($this->isOSX()) {
            $this->message("OSX: Extracting tar.gz file: " . $this->tarfile);
            $cmd = "tar xfz " . $this->tarfile . " -C /usr/local/opt ";
            exec($cmd, $output, $returnValue);
            if($returnValue != 0) {
                $this->error("Failed to extract file " . $this->tarfile . " to /usr/local/opt", 4);
            }

        }
        else {
            $this->message("Extracting tar.gz file: " . $this->tarfile);
            $cmd = "tar xfz " . $this->tarfile . " -C /opt";
            exec($cmd, $output, $returnValue);
            if ($returnValue != 0) {
                $this->error("Failed to extract file " . $this->tarfile . " to /opt", 4);
            }
        }

        $this->installZRayUIConfigFile();

        if ($this->isLinux()) {
            $this->message("ln -sf {$this->targetFolder}/lib/zray.so " . $this->extensionsDir . "/zray.so");
            exec("ln -sf {$this->targetFolder}/lib/zray.so " . $this->extensionsDir . "/zray.so");

            $this->message("ln -sf {$this->targetFolder}/zray.ini " . $this->webServerScanDir . "/zray.ini");
            exec("ln -sf {$this->targetFolder}/zray.ini " . $this->webServerScanDir . "/zray.ini");

            if ($this->isDebian()) {
                $this->message("a2ensite zray-ui.conf");
                exec("a2ensite zray-ui.conf");

                $this->message("ln -sf {$this->targetFolder}/zray.ini " . $this->scanDirCli . "/zray.ini");
                exec("ln -sf {$this->targetFolder}/zray.ini " . $this->scanDirCli . "/zray.ini");
            }

            if ($this->getIsNginx()) {
                $nginxDefaultDir = '/usr/share/nginx/html';
                if (!file_exists($nginxDefaultDir)) {
                    $nginxDefaultDir = '/usr/share/nginx/www';
                }

                $this->message("ln -sf {$this->targetFolder}/zray.ini " . $this->nginxPath . "/zray.ini");
                exec("ln -sf {$this->targetFolder}/zray.ini " . $this->nginxPath . "/zray.ini");

                $this->message("ln -sf {$this->targetFolder}/gui/public $nginxDefaultDir/ZendServer ");
                exec("ln -sf {$this->targetFolder}/gui/public $nginxDefaultDir/ZendServer");
            }

            // Run php -m, this will create all the directory layout
            exec("php -m", $phpMOutput);

        }
        else if($this->isOSX()) {
            // Edit php.ini
            if ($this->OSXIsPhpIniNeeded()) {
                $phpIniLoadedFile = php_ini_loaded_file();
                if (empty($phpIniLoadedFile)) {
                    $phpIniLoadedFile = '/etc/php.ini';
                }

                $this->message("Editing php.ini: " . $phpIniLoadedFile);
                $dataToAdd = "\nzend_extension={$this->targetFolder}/lib/zray.so\nzray.install_dir={$this->targetFolder}\n";
                file_put_contents($phpIniLoadedFile, $dataToAdd, FILE_APPEND);
            }
            else {
                $this->message("No need to edit php.ini ". php_ini_loaded_file());
            }

            // Run php -m, this will create all the directory layout
            exec("/usr/local/bin/php -m", $phpMOutput);

            // Finally, run install_name_tool to ensure that libphp5.so is loading the proper
            // libcurl.4.dylib
            $libphp = $this->OSXGetPhpExtPath();
            if ($libphp) {
                $installNameToolCmd = "install_name_tool -change /usr/lib/libcurl.4.dylib {$this->targetFolder}/lib/libcurl.4.dylib $libphp";
                $this->message($installNameToolCmd);
                exec($installNameToolCmd);

                $installNameToolCmd = "install_name_tool -change /opt/zray/lib/libcurl.4.dylib {$this->targetFolder}/lib/libcurl.4.dylib $libphp";
                $this->message($installNameToolCmd);
                exec($installNameToolCmd);
            }

            // Fix zdd
            $installNameToolCmd = "install_name_tool -change libZendDevBarLib.so {$this->targetFolder}/lib/libZendDevBarLib.so {$this->targetFolder}/bin/zdd";
            $this->message($installNameToolCmd);
            exec($installNameToolCmd);
        }

        // last step: execute zdd to populate the plugins database
        $this->message("Deploying Z-Ray plugins...");
        $this->message("{$this->targetFolder}/bin/zdd {$this->targetFolder}/runtime/etc/zdd.ini -e --cli");
        exec("{$this->targetFolder}/bin/zdd {$this->targetFolder}/runtime/etc/zdd.ini -e --cli");

        // let zdd start and (so it will create log files etc)
        // and then call chown command
        sleep(1);
        $this->message("Deploying Z-Ray plugins... done");

        $this->chownDirectory();

        // and finally restart the web server
        $this->restartWebServer();
    }

    /**
     * @brief restart the web server
     */
    protected function restartWebServer() {
        if ($this->isApache()) {
            if ($this->isOSX()) {
                // restart php
                $this->message("/usr/sbin/apachectl restart");
                exec("/usr/sbin/apachectl restart");

            }
            else if($this->isCentOS() || $this->isRHEL()) {
                $this->message("systemctl restart httpd");
                exec("systemctl restart httpd");

            }
            else {
                // Last, restart apache
                $this->message("service apache2 restart");
                exec("service apache2 restart");
            }
        } else {
            // Nginx
            if ($this->isCentOS() || $this->isRHEL()) {
                $this->message("systemctl restart php-fpm");
                exec("systemctl restart php-fpm");

                $this->message("systemctl restart nginx");
                exec("systemctl restart nginx");
            }
            else {
                $this->message("/etc/init.d/nginx stop");
                exec("/etc/init.d/nginx stop");

                $this->message("service php5-fpm stop");
                exec("service php5-fpm stop");

                $this->message("service php5-fpm start");
                exec("service php5-fpm start");

                $this->message("/etc/init.d/nginx start");
                exec("/etc/init.d/nginx start");
            }
        }
    }

    /**
     * @brief chown the zray directory
     */
    protected function chownDirectory() {
        if ($this->isOSX()) {
            $this->message("/usr/sbin/chown -R {$this->webuser}:{$this->webgroup} {$this->targetFolder}");
            exec("/usr/sbin/chown -R {$this->webuser}:{$this->webgroup} {$this->targetFolder}");
        }
        else {
            $this->message("chown -R {$this->webuser}:{$this->webgroup} {$this->targetFolder}");
            exec("chown -R {$this->webuser}:{$this->webgroup} {$this->targetFolder}");
        }
    }
    /**
     * @brief locate the location of the libphp5.so
     * @return
     */
    protected function OSXGetPhpExtPath() {
        // to do this, we open the apache configuration file
        // and search for the line similar to
        // LoadModule php5_module /usr/local/opt/php56/libexec/apache2/libphp5.so
        $lines = file("/private/etc/apache2/httpd.conf");
        foreach($lines as $line) {
            $line = trim($line);
            if(strpos($line, "#") === 0) {
                // comment line, skip it
                continue;
            }
            if(strstr($line, "LoadModule") && strstr($line, "php5_module")) {
                // we found our line, split it by spaces
                $line = str_replace("\t", " ", $line);
                $parts = explode(" ", $line);
                if(count($parts) >= 3) {
                    return $parts[2];
                }
            }
        }
        return false;
    }

    /**
     * @brief do we need to edit php.ini by adding zend_extension={$this->targetFolder}/lib/zray.so ?
     * @return
     */
    protected function OSXIsPhpIniNeeded() {
        // Edit the php.ini file and add zend_extension={$this->targetFolder}/lib/zray.so
        $loadedIniFile = php_ini_loaded_file();
        if (empty($loadedIniFile)) {
            return true;
        }

        $iniFile = parse_ini_file($loadedIniFile);
        foreach($iniFile as $k => $v) {
            if($v == "{$this->targetFolder}/lib/zray.so") {
                return false;
            }
        }
        return true;
    }


    /**
     * @brief show an informative message
     * @param string $msg
     */
    public function message($msg) {
        echo ".. " . $msg . "\n";
    }

    /**
     * @brief show an error message and exit with $exitCode
     * @param string $msg
     * @param int $exitCode
     */
    public function error($msg, $exitCode) {
        echo ".. ERROR: " . $msg . "\n";
        usage($exitCode);
    }
    /**
     * @param  $isNginx
     * @return \Installer
     */
    public function setIsNginx($isNginx) {
        $this->isNginx = $isNginx;
        return $this;
    }
    /**
     * @return
     */
    public function getIsNginx() {
        return $this->isNginx;
    }
    /**
     * @param  $nginxPath
     * @return \Installer
     */
    public function setNginxPath($nginxPath) {
        $this->nginxPath = $nginxPath;
        return $this;
    }
    /**
     * @return
     */
    public function getNginxPath() {
        return $this->nginxPath;
    }
}

ini_set("output_buffering", "Off");

if($argc < 2) {
    usage(-1);
}

$isNginx = false;
$nginxConfig = "";

if ($argc > 2) {
    // we have got nginx argument
    $isNginx = true;
    $where = strpos($argv[2], "nginx=");
    if ($where !== FALSE) {
        $nginxConfig = substr($argv[2], $where + 6);
    }
}

// Disable Z-Ray
if(function_exists("zray_disable")) {
    zray_disable(true);
}

// Create the installer and initialize it
$installer = new Installer($isNginx);
$installer->setTarFile($argv[1])->setIsNginx($isNginx)->setNginxPath($nginxConfig);

// Perform sanity + installation
$installer->install();
$installer->message("Installation completed successfully!");
