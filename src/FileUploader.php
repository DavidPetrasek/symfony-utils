<?php
namespace Psys\SymfonyUtils;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;


class FileUploader
{    
    public function __construct
    (
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
        private Filesystem $filesystem,
        private $projectDir,
    )
    {}
    
    public function saveFile(UploadedFile $file, $relTargetDir, $stripMetadata = false, $customFileSystemName = '')
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $mimeType = $file->getMimeType();
        $extension = $file->guessExtension();
        $absTargetDir = $this->projectDir.$relTargetDir;
        
        if (empty($customFileSystemName))
        {
            try 
            {
                $absPath = $this->filesystem->tempnam($absTargetDir, '', '.'.$extension);
            } 
            catch (IOExceptionInterface $exception) 
            {
                $this->logger->error("Filesystem exception occured when generating new temporary file", [$exception->getMessage()]);
            }
            
            $nameFileSystem = basename($absPath);  
        }
        else
        {
            $nameFileSystem = $customFileSystemName;
        }

        try 
        {
            $file->move($absTargetDir, $nameFileSystem);
            
            if ($stripMetadata) {$this->stripMetadata ($absTargetDir.'/'.$nameFileSystem, $mimeType, $extension);}
        } 
        catch (FileException $e) 
        {
            $this->logger->error("Filesystem exception occured when replacing temporary file with uploded file", [$exception->getMessage()]);
        }
        
        return 
        [
            'nameFileSystem' => $nameFileSystem,
            'nameDisplay' => $safeFilename.'.'.$extension,
            'mimeType' => $mimeType,
        ];
    }
    
    private function stripMetadata ($fileAbsPath, $mimeType, $extension)
    {        
        if ($mimeType === 'image/jpeg' || $mimeType === 'image/png') 
        {
            try
            {
                $imagick = new \Imagick();
                $imagick->readImage( $fileAbsPath );
                
                // Keep color profile
                $profiles = $imagick->getImageProfiles("icc", true);
                $imagick->stripImage();
                if ( !empty($profiles) ) {$imagick->profileImage("icc", $profiles['icc']);}
                
                $imagick->writeImage($fileAbsPath);
            }
            catch (\Exception $e)
            {
                $this->logger->error("Imagick exception occured when removing metadata from file $fileAbsPath", [$e->getMessage()]);
            }
        }
        
        else if ($mimeType === 'application/pdf')
        {            
            try
            {
                $imagick = new \Imagick();
                $imagick->setResolution( 250, 250 );
                $imagick->readImage( $fileAbsPath );                
//                 $pages = (int)$imagick->getNumberImages(); 
                
                // Save all pages as temporary JPGs
                $absCestyTemp = [];
                foreach ($imagick as $i => $im) 
                {                                            //
                    $im->setImageFormat('jpeg');
                    $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
                    $im->setImageCompressionQuality(80);
                    
                    try
                    {
                        $absPath = $this->filesystem->tempnam(sys_get_temp_dir(), '', '.jpg');
                    }
                    catch (IOExceptionInterface $exception)
                    {
                        $this->logger->error("Filesystem exception occured when generating new temporary file", [$exception->getMessage()]);
                    }
                    
                    $im->writeImage($absPath);
                    $absCestyTemp[] = $absPath;
                }                
//                 
                $imagick->clear();
                
                // New PDF from temporary JPGs
                $imagick = new \Imagick($absCestyTemp);
                $imagick->setImageFormat('pdf');
                $imagick->writeImages($fileAbsPath, true); 
                $imagick->clear();
                
                // Remove temporary JPGs
                try
                {
                    $this->filesystem->remove($absCestyTemp);
                }
                catch (IOExceptionInterface $exception)
                {
                    $this->logger->error("Filesystem exception occured when removing temporary file", [$exception->getMessage()]);
                }
            }
            catch (\Exception $e)
            {
                $this->logger->error("Imagick exception occured when removing metadata from file $fileAbsPath", [$e->getMessage()]);
            }
        }
        
        else if ($mimeType === 'video/mp4' || $mimeType === 'audio/mpeg')
        {                
            try 
            {
                $process = new Process(
                    [
                        "/usr/bin/ffmpeg",
                        '-i', 
                        $fileAbsPath, 
                        '-map_metadata', 
                        '-1', 
                        '-c:v', 
                        'copy',
                        '-c:a',
                        'copy',
                        $fileAbsPath.'_ffmpeg_output.'.$extension
                    ]);
                $process->run();
                
                // executes after the command finishes
                if (!$process->isSuccessful()) 
                {
                    throw new ProcessFailedException($process);
                }
                else
                {
                    $this->filesystem->rename($fileAbsPath.'_ffmpeg_output.'.$extension, $fileAbsPath, true);
                }
            } 
            catch (ProcessFailedException $e) 
            {
                $this->logger->error("Process exception occured when removing metadata from file $fileAbsPath", [$e->getMessage()]);
            }
        }
    }
}


?>