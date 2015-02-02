<?php
/**
 * Created by PhpStorm.
 * User: markcyrulik
 * Date: 1/31/15
 * Time: 9:19 AM
 */
namespace Mcyrulik\VHostBackup;

use Touki\FTP\Connection\Connection;
use Touki\FTP\FTP;
use Touki\FTP\FTPFactory;
use Touki\FTP\Model\Directory;
use Touki\FTP\Model\File;

class VHostBackup
{
    private $options = array();
    private $debug = false;
    private $temp_doc_location;
    private $temp_db_location;

    private $ftp_connection = null;

    /** @var $ftp_factory  \Touki\FTP\FTPFactory */
    private $ftp_factory = null;

    /** @var $ftp  \Touki\FTP\FTP */
    private $ftp = null;

    private $db_connection;

    public function setDebug($debug)
    {
        $this->debug = $debug;
    }


    /**
     * @param $option - The option that we want to set
     * @param $value - The value for that option.
     *
     * @return BOOL - whether the option was successfully set.
     */
    public function setOption($option, $value)
    {
        // Only checking option.. if value is null, then we can unset the option.
        if (empty($option)) {
            return false;
        }

        if ($value == null) {
            unset($this->options[$option]);
            return !isset($this->options[$option]);
        }

        $this->options[$option] = $value;
        return isset($this->options[$option]);
    }

    /**
     * @param $option
     *
     * @return mixed - The value for the option, or null if the option is not set.
     */
    public function getOption($option)
    {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        } else {
            return null;
        }
    }

    /**
     * @return array - Array of the directories for the user directory that must be set as an option.
     * @throws Exception
     */
    public function getUserDirectoryList()
    {
        if (!isset($this->options['user_dir_root']) || empty($this->options['user_dir_root'])) {
            throw new Exception("User directory root cannot be null.");
        }

        $scanned_directory = array_diff(scandir($this->options['user_dir_root']), array('..', '.'));

        $return_array = array();

        foreach ($scanned_directory as $dir) {
            if (is_dir($this->options['user_dir_root']."/".$dir)) {
                $temp = array(
                    'dir' => $dir,
                    'full_path' => $this->options['user_dir_root'] . $dir . "/"
                );
                array_push($return_array, $temp);
            }
        }

        return $return_array;
    }

    /**
     * @param $directory - directory where the temp archives should be stored.
     *
     * @return bool - whether or not the directory was set correctly.
     */
    public function setLocalDocumentArchiveLocation($directory)
    {
        if (!is_dir($directory)) {
            // @todo: Tell the user that this is not a directory.
            return false;
        }

        if (!is_writeable($directory)) {
            // @todo: Tell the user that the directory is not writeable.
            return false;
        }

        $this->temp_doc_location = $this->fixPath($directory);
        return true;
    }

    /**
     * @return mixed
     */
    public function getLocalDocumentArchiveLocation()
    {
        return $this->temp_doc_location;
    }

    /**
     * @param $directory - directory where the temp archives should be stored.
     *
     * @return bool - whether or not the directory was set correctly.
     */
    public function setLocalDatabaseArchiveLocation($directory)
    {
        if (!is_dir($directory)) {
            // @todo: Tell the user that this is not a directory.
            return false;
        }

        if (!is_writeable($directory)) {
            // @todo: Tell the user that the directory is not writeable.
            return false;
        }

        $this->temp_db_location = $this->fixPath($directory);
        return true;
    }

    /**
     * @return mixed
     */
    public function getLocalDatabaseArchiveLocation()
    {
        return $this->temp_db_location;
    }

    /**
     * @param $directory - The directory to be archived.
     * @param $archive_name - the output file name
     *
     * @return bool - whether an archive with the $archive_name existed at the temp_doc_location.
     */
    public function archiveDirectory($directory, $archive_name)
    {
        if (empty($this->temp_doc_location)) {
            // @todo: throw an exception, or something if there is no temp directory set.
            echo "no temp location";
            return false;
        }

        if ($archive_name == null) {
            // @todo: exception to the user..
            echo "no archive name";
            return false;
        }

        if (isset($this->options['sub_dir']) && !empty($this->options['sub_dir'])) {
            $zip_dir = $this->fixPath($directory).$this->fixPath($this->options['sub_dir']);
        } else {
            $zip_dir = $this->fixPath($directory);
        }
        if ($this->debug) {
            echo "Archiving ".$zip_dir." to ".$archive_name."...";
        }
        $shell_command = "cd {$zip_dir}; zip -r {$this->temp_doc_location}{$archive_name} ./";
        @shell_exec($shell_command);

        if (file_exists($this->temp_doc_location.$archive_name)) {
            if ($this->debug) {
                echo "Success!".PHP_EOL;;
            }
            return true;
        } else {
            if ($this->debug) {
                echo "Error: Archive not created.".PHP_EOL;;
            }
            return false;
        }
    }

    /**
     * @return array
     */
    public function getTempDocLocationFileList()
    {
        if (!is_dir($this->temp_doc_location)) {
            return array();
        }

        $return_array = array();

        $scanned_directory = array_diff(scandir($this->temp_doc_location), array('..', '.'));

        foreach ($scanned_directory as $archive) {
            if (file_exists($this->temp_doc_location."/".$archive)) {
                $temp = array(
                    'file_name' => $archive,
                    'full_path' => $this->temp_doc_location . $archive
                );
                array_push($return_array, $temp);
            }
        }
        return $return_array;
    }

    /**
     * @return array
     */
    public function getTempDBLocationFileList()
    {
        if (!is_dir($this->temp_db_location)) {
            return array();
        }

        $return_array = array();

        $scanned_directory = array_diff(scandir($this->temp_db_location), array('..', '.'));

        foreach ($scanned_directory as $archive) {
            if (file_exists($this->temp_db_location."/".$archive)) {
                $temp = array(
                    'file_name' => $archive,
                    'full_path' => $this->temp_db_location . $archive
                );
                array_push($return_array, $temp);
            }
        }
        return $return_array;
    }

    public function uploadFile($local_file, $remote_file, $remote_path = null)
    {
        // if the factory isn't set up yet, or the property is not the right type, then let's try to set it.
        if ($this->ftp == null || !($this->ftp instanceof FTP)) {
            $this->createFTPConnection();
        }

        if ($this->debug) {
            echo "Local File: " . $local_file . " --> " . $this->fixPath($remote_path) . $remote_file . "...";
        }

        $remote_dir = new Directory($remote_path);
        if (!$this->ftp->directoryExists($remote_dir)) {
            if ($this->debug) {
                echo "creating remote directory...";
            }
            // If the remote directory does not exist, then we need to create it.
            $this->ftp->create($remote_dir);
        } else {
            if ($this->debug) {
                if ($this->debug) {
                    echo "directory exists...";
                }
            }
        }

        if ($this->debug) {
            echo "Uploading..";
        }
        $this->ftp->upload(new File($this->fixPath($remote_path).$remote_file), $local_file);
        if ($this->debug) {
            echo "Success!".PHP_EOL;
        }

        return true;
    }

    private function createFTPConnection()
    {
        if (!isset($this->options['ftp_host']) || empty($this->options['ftp_host']) ||
            !isset($this->options['ftp_user']) || empty($this->options['ftp_user']) ||
            !isset($this->options['ftp_pass']) || empty($this->options['ftp_pass'])
        ) {
            return false;
        }

        $this->ftp_connection = new Connection($this->options['ftp_host'], $this->options['ftp_user'], $this->options['ftp_pass']);
        $this->ftp_factory = new FTPFactory;
        $this->ftp_factory->build($this->ftp_connection);
        $this->ftp = new FTP(
            $this->ftp_factory->getManager(),
            $this->ftp_factory->getDownloaderVoter(),
            $this->ftp_factory->getUploaderVoter(),
            $this->ftp_factory->getCreatorVoter(),
            $this->ftp_factory->getDeleterVoter()
        );

        return true;
    }

    private function fixPath($path)
    {
        return rtrim($path, '/') . '/';
    }

    private function createDBConnection()
    {
        if (!isset($this->options['db_host']) || empty($this->options['db_host']) ||
            !isset($this->options['db_user']) || empty($this->options['db_user']) ||
            !isset($this->options['db_pass']) || empty($this->options['db_pass'])
        ) {
            return false;
        }
        $this->db_connection = new \PDO("mysql:host=".$this->options['db_host'], $this->options['db_user'], $this->options['db_pass']);

        return true;
    }
    public function getDatabaseList()
    {
        if ($this->db_connection == null || !($this->db_connection instanceof \PDO)) {
            $this->createDBConnection();
        }

        $sql = 'SHOW DATABASES;';

        $return_array = array();

        foreach ($this->db_connection->query($sql) as $row) {
            $database = $row['Database'];

            // A few databases that we dont want/need to back up,
            if ($database == 'information_schema' || $database == 'performance_schema') {
                continue;
            }

            array_push($return_array, $database);
        }

        return $return_array;
    }

    public function archiveDatabase($db_name, $archive_name)
    {
        if (empty($this->temp_db_location)) {
            // @todo: throw an exception, or something if there is no temp directory set.
            echo "no temp location";
            return false;
        }

        if ($archive_name == null) {
            // @todo: exception to the user..
            echo "no archive name";
            return false;
        }

        if ($this->db_connection == null || !($this->db_connection instanceof \PDO)) {
            $this->createDBConnection();
        }

        if ($this->debug) {
            echo "Exporting Database: {$db_name} --> {$this->temp_db_location}{$archive_name}...";
        }
        $shell_command = "MYSQL_PWD={$this->options['db_pass']} mysqldump {$db_name} -h {$this->options['db_host']} -u'{$this->options['db_user']}' | gzip -c | cat > {$this->temp_db_location}{$archive_name}";
        @shell_exec($shell_command);
        if ($this->debug) {
            echo "Success!" . PHP_EOL;
        }
    }

    public function cleanUpDirectory($directory)
    {
        $scanned_directory = array_diff(scandir($directory), array('..', '.'));

        foreach ($scanned_directory as $file) {
            $this->cleanUpFile($directory.$file);
        }
    }

    public function cleanUpFile($filename)
    {
        if ($this->debug) {
            echo "Unlinking: " . $filename . PHP_EOL;
        }
        unlink($filename);
    }
}