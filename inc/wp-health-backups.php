<?php
class WPHealthBackups
{
    private $backup_files = array();
    private $backups_size = 0;

    public $viewTypes = array(
        'plugins',
        'themes',
        'uploads',
        'others',
        'db'
    );

    public $last_months = 1;

    public function getBackupsData(){
        $backup_list = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
        if (empty($backup_list)) return;

        $backups = array();
        foreach($backup_list as $timestamp => $backups_info){
            $time_mm =  strtotime("-$this->last_months month");
            // If date more than 1 month - skip it
            if(date('Y-m-d', $timestamp) < date('Y-m-d', $time_mm))
                continue;

            $this->backups_size = 0;
            $this->backup_files = array();
            $destinations = array();

            if(is_array($backups_info)){

                if($this->viewTypes && is_array($this->viewTypes)){
                    foreach($this->viewTypes as $type){
                        $this->_addBackupElement($backups_info, $type);
                    }
                }

                if(isset($backups_info['service']) && !empty($backups_info['service'])){
                    if(is_array($backups_info['service']))
                        foreach($backups_info['service'] as $service){
                            if($service == 'none')
                                $service = 'local';
                            $destinations[] = $service;
                        }
                    else {
                        if($backups_info['service'] == 'none')
                            $backups_info['service'] = 'local';
                        $destinations[] = $backups_info['service'];
                    }
                }

                if(isset($this->backup_files) && is_array($this->backup_files) && !empty($this->backup_files))
                    $backups[date('Y-m-d', $timestamp)][] = array(
                        'timestamp' => $timestamp,
                        'file' => $this->backup_files,
                        'dest' => $destinations,
                        'source' => null,
                        'size' => $this->backups_size,
                        'links' => array()
                    );
            }
        }
        return $backups;
    }

    private function _addBackupElement($backups_info, $type){
        if(isset($backups_info[$type]) && !empty($backups_info[$type])){
            if(isset($backups_info[$type.'-size']) && !empty($backups_info[$type.'-size']))
                $this->backups_size += (int) $backups_info[$type.'-size'];
            if(is_array($backups_info[$type]))
                foreach($backups_info[$type] as $file){
                    $this->backup_files[] = $file;
                }
            else $this->backup_files[] = $backups_info[$type];
        }
    }
}