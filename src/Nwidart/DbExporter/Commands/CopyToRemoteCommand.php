<?php namespace Nwidart\DbExporter\Commands;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use SSH;
use Config;

class CopyToRemoteCommand extends GeneratorCommand
{

    protected $name = 'dbe:remote';
    protected $description = 'Command to copy the migrations and/or the seeds to a remote host.';

    protected $ignoredFiles =  array('..', '.', '.gitkeep');

    protected $migrationsPath;
    protected $seedsPath;
    protected $uploadedFiles;
    protected $commandOptions;

    public function __construct()
    {
        parent::__construct();

        // Set the paths
        $this->migrationsPath = app_path() . "/database/migrations";
        $this->seedsPath = app_path() . "/database/seeds";
    }

    public function fire()
    {
        $succes = $this->handleOptions();
        if ($succes) {
            // Inform what files have been uploaded
            foreach ($this->uploadedFiles as $type => $files) {
                $this->info(ucfirst($type));
                foreach ($files as $file) {
                    $this->sectionMessage($type, $file .' uploaded.');
                }
            }

            $this->blockMessage('Success!', 'Everything uploaded!');
        }
    }

    protected function getArguments()
    {
        return array(
            array('remote', InputArgument::REQUIRED, 'The remote name.')
        );
    }

    protected function getOptions()
    {
        return array(
            array('migrations', 'mig', InputOption::VALUE_NONE, 'Upload the migrations to the remote host.', null),
            array('seeds', 'seed', InputOption::VALUE_NONE, 'Upload the seeds to the remote host.', null)
        );
    }

    private function getRemoteName()
    {
        // Use default production key
        if (!$this->argument('remote')) {
            return 'production';
        } else {
            return $this->argument('remote');
        }
    }

    private function handleOptions()
    {
        $options = $this->option();
        switch ($options) {
            case (($options['seeds'] === true) and ($options['migrations'] === true)):
                $this->upload('migrations');
                return $this->upload('seeds');
                break;
            case $options['migrations'] === true:
                $this->commandOptions = 'migrations';
                return $this->upload('migrations');
                break;
            case $options['seeds'] === true:
                $this->commandOptions = 'seeds';
                return $this->upload('seeds');
                break;
        }
    }

    private function upload($what)
    {
        $localPath = "{$what}Path";

        $dir = scandir($this->$localPath);
        $remotePath = Config::get('db-exporter::remote.'.$what);

        // Prepare the progress bar
        $progress = $this->getHelperSet()->get('progress');
        $filesCount = count($dir) - count($this->ignoredFiles);
        $progress->start($this->output, $filesCount);

        foreach($dir as $file) {
            if (in_array($file, $this->ignoredFiles)) {
                continue;
            }

            // Capture the uploaded files for displaying later
            $this->uploadedFiles[$what][] = $remotePath . $file;

            // Copy the files
            SSH::into($this->getRemoteName())->put(
                $this->$localPath .'/' . $file,
                $remotePath . $file
            );
            $progress->advance();
        }
        $progress->finish();

        return true;
    }
}