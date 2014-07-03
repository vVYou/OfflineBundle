<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Claroline\OfflineBundle\SyncConstant;
use \ZipArchive;

class InstallOfflineCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('claroffline:generate:install')
            ->setDescription('Create a new archive based on a specific platform')
            // ->setDefinition(
                // array(
                    // new InputArgument('platform_path', InputArgument::REQUIRED, 'The path of the platform'),
                    // new InputArgument('new_component_path', InputArgument::OPTIONAL, 'Sets a new path for the component')
                // )
            // )
            ->addArgument(
                'platform_path', 
                InputArgument::REQUIRED, 
                'The path of the platform'
            )
            ->addArgument(
                'new_component_path', 
                InputArgument::OPTIONAL, 
                'Sets a new path for the component'
            )
            ->addOption(
                'windows',
                'w',
                InputOption::VALUE_NONE,
                'Make the result of this command compatible with Windows Operating System.'
            )
            ->addOption(
                'linux',
                'l',
                InputOption::VALUE_NONE,
                'Make the result of this command compatible with Linux Operating System.'
            );
    }       

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $platform_path = $input->getArgument('platform_path');
        $component_path = $input->getArgument('new_component_path');
        $no_file = $this->buildNoFileArray($platform_path);
        // print($platform_path);
        // foreach($elem as $no_file){
            // print('BONJOUR');
        // }
        
        ini_set('max_execution_time', 0);
        $archive = new ZipArchive;
            if($input->getOption('windows'))
            {
                // Call to the method for Windows Operating System
                if ($archive->open('./claroffline_win.zip', ZipArchive::OVERWRITE)) 
                {
                    $archive = $this->createForWindows($platform_path, $component_path, $archive, $no_file);
                    $archive->close();
                }
                else
                {
                    print('FAIL');
                }
            }
            else
            {
               // Call to the method for Linux Operating System            
            }
            

    }
    
    protected function createForWindows($platform_path, $component_path, ZipArchive $archive, array $no_file)
    {
        if($component_path == "")
        {
            $component_path = SyncConstant::COMP_PATH_WIN;
        }
        
        // Put XAMPP and Chrome Portable in the Archive
        $this->loadDirectory($component_path, $platform_path, $archive);
        
        // Put Platform in the XAMPP directory
        $this->loadPlatform($platform_path, $component_path, $archive, $no_file);
        return $archive;
    }
    
    protected function createForLinux()
    {
    
    }
    
    /*
    *   Source inspiration : PHP Manual
    *   http://www.php.net/manual/fr/ziparchive.addfile.php
    */
    function loadDirectory($path, $platform_path, ZipArchive $zip)
    {
        // $xampp_htdocs = $path.'/xampp/htdocs';
        $nodes = glob($path . '/*');
        foreach ($nodes as $node) 
        {      
            if (is_dir($node)) {           
                $zip->addEmptyDir(substr($node, strlen(SyncConstant::COMP_PATH_WIN)+1));
                $this->loadDirectory($node, $platform_path, $zip);
            } else if (is_file($node))  {
                $zip->addFile($node, substr($node, strlen(SyncConstant::COMP_PATH_WIN)+1));
            }            
        }
        // print($path);
        // $zip->addEmptyDir("yolo");
        return $zip;
    }

    function loadPlatform($path, $component_path, ZipArchive $zip, array $no_file)
    {   
        $nodes = glob($path . '/*');
        foreach ($nodes as $node) 
        {
            $in_it = $this->isInNoFileArray($node, $no_file);
            if (is_dir($node) & !($in_it)) {
                $zip->addEmptyDir('xampp/htdocs/'.$node);
                $this->loadPlatform($node, $component_path, $zip, $no_file);
            } else if (is_file($node) & !($in_it))  {
                // print('Mon Node : '.$node);
                $zip->addFile('xampp/htdoc/'.$node);
            }
            
        }

        return $zip;
    }

    function buildNoFileArray($path)
    {
        /*
        *   Build an array with the path for app/cache, app/log, ... that doesn't need
        *   to be in a empty copy of the platform.
        */
        return array(
        $path.SyncConstant::APP_CACHE,
        $path.SyncConstant::LOG,
        $path.SyncConstant::SYNCHRO_UP_DIR,
        $path.SyncConstant::SYNCHRO_DOWN_DIR,
        $path.SyncConstant::PLAT_FILES
        );           
    }
    
    function isInNoFileArray($path, $no_file)
    {
        foreach($no_file as $elem)
        {
            if (strpos($path, $elem) !== false)
            {
                return true;
            }
        }        
        return false;
    }    
    
    function dataBaseCons(ZipArchive $zip)
    {
        $cmd = "";
        // create a databse = dans mysql > CREATE DATABSE db_name
        // mysqldump -u root -p -d NomDBaDump > fichier (dans parameter.yml)
        // WIN : type fichier | mysql -u root -p -D new_db (nouvelle database)
        // LIN : cat fichier | mysql -u root -p -D new_db (nouvelle databse)
        
    }
    
}
