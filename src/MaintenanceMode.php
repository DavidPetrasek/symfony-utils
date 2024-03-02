<?php

namespace Psys\SymfonyUtils;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

use Doctrine\Persistence\ManagerRegistry;

use App\Repository\MaintenanceModeRepository;


class MaintenanceMode
{
    private $maintenanceModeSettings = [];
    
    public function __construct
    (
        private MaintenanceModeRepository $maintenanceModeRepository,
        private ManagerRegistry $doctrine,
        private Security $security,
        private Filesystem $filesystem, 
        private RequestStack $requestStack,
        private $projectDir
    )
    {
        $this->maintenanceModeSettings = $maintenanceModeRepository->getAll();
    }
    
    
    private function isPlanned (): bool
    {   
        return !empty($this->maintenanceModeSettings['enable_at']);
    }
    
    private function isEnabled (): bool
    {
        return $this->maintenanceModeSettings['enabled'] == 1;
    }
    
    public function check ()
    {        
        $resp = 'is_not_planned';        
        if (!$this->isPlanned()) {return $resp;}
        
        $dt_now = new \DateTimeImmutable();     //
        $dt_now = $dt_now->setTime ( $dt_now->format("H"), $dt_now->format("i"), '00' );        //        
        
        $dt_logout_user_at = new \DateTimeImmutable($this->maintenanceModeSettings['enable_at']);
        $dt_logout_user_at = $dt_logout_user_at->setTime ( $dt_logout_user_at->format("H"), $dt_logout_user_at->format("i"), '00' );    //     
        $dt_enable_at = $dt_logout_user_at->setTime ( $dt_logout_user_at->format("H"), $dt_logout_user_at->format("i"), '10' );    //
                
        if ($dt_now < $dt_logout_user_at)
        {
            $dt_diff = $dt_now->diff($dt_logout_user_at);
            
            if      ( $dt_diff->i === 1 ) {$minTvar = 'minutu';}
            else if ( $dt_diff->i > 1  &&  $dt_diff->i < 5 ) {$minTvar = 'minuty';}
            else if ( $dt_diff->i >= 5 ) {$minTvar = 'minut';}
            
            $dt_estimated_end_at = new \DateTimeImmutable($this->maintenanceModeSettings['estimated_end_at']);
            $formatter_estimated_end_at = new \IntlDateFormatter('cs_CZ', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, null, null, "HH:mm");
            $estimated_end_at_text = $formatter_estimated_end_at->format( $dt_estimated_end_at );    
            
            $resp = ['message_notify_before' => 'Režim údržby začně za '.$dt_diff->i.' '.$minTvar.'.<br> Předpokládaný konec: '.$estimated_end_at_text];
        }
        
        // Log out user if time is up
        else if ($dt_now >= $dt_logout_user_at  &&  $dt_now < $dt_enable_at  &&  !$this->isEnabled())
        {            
            if (!$this->ipOnWhitelist ($this->requestStack->getCurrentRequest(), $this->maintenanceModeSettings))
            {
                if ($this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) 
                {
                    $logoutResponse = $this->security->logout();
                }
            }
            
            $resp = ['user_was_logged_out' => true];
        }
        
        // Turn on after user was logged out
        else if ($dt_now >= $dt_enable_at  &&  !$this->isEnabled())
        {
            $this->enable();
            
            if (!$this->ipOnWhitelist ($this->requestStack->getCurrentRequest(), $this->maintenanceModeSettings))
            {                
                $resp = ['was_enabled_reload' => true];
            }
            else 
            {
                $resp = ['was_enabled' => true];
            }
        }
        
        return $resp;
    }
    
    private function enable ()
    {   
        if ($this->isEnabled()) {return;}
        
        $this->maintenanceModeSettings['enabled'] = 1;
        $this->save ($this->maintenanceModeSettings);
        
        $this->filesystem->rename($this->projectDir.'/public/.htaccess', $this->projectDir.'/public/htaccess'); 
        
//         
//         return;
        
        // Create new .htaccess from the current onee
        $orig_htaccess_content = file_get_contents($this->projectDir.'/public/htaccess');
        $mm_htaccess_prepend_content =
"# Deny access to .htaccess
<Files .htaccess>
Require all denied
</Files>

RewriteEngine On

# Maintenance mode except whitelisted IP's
RewriteCond %{REMOTE_ADDR} !^0\.0\.0\.0$
RewriteCond %{REMOTE_ADDR} !^127\.0\.0\.2$
RewriteCond %{REQUEST_FILENAME} !\.(css|js|png|jpg|svg)$
RewriteRule ^.*$ maintenance-mode/index.html [L]".PHP_EOL.PHP_EOL;
        
        $this->filesystem->dumpFile($this->projectDir.'/public/.htaccess', $mm_htaccess_prepend_content.$orig_htaccess_content);
        
        if ($_SERVER['APP_ENV'] === 'prod')
        {
            $mmFilePermission = 0400;
        }
        else if ($_SERVER['APP_ENV'] === 'dev')
        {
            $mmFilePermission = 0646;
        }
        $this->filesystem->chmod($this->projectDir.'/public/.htaccess', $mmFilePermission);
    }
    
    public function disable ()
    {
        if (!$this->isEnabled()) {return $this->json([]);}
        
        $this->maintenanceModeSettings['enabled'] = 0;
        $this->maintenanceModeSettings['enable_at'] = '';
        $this->maintenanceModeSettings['estimated_end_at'] = '';
        
        $this->save ($this->maintenanceModeSettings);
        
        $this->filesystem->remove($this->projectDir.'/public/.htaccess');
        $this->filesystem->rename($this->projectDir.'/public/htaccess', $this->projectDir.'/public/.htaccess');
    }
    
    public function save ($maintenanceModeSettings)
    {
        $entityManager = $this->doctrine->getManager();
        $maintenanceModeEntity = $this->maintenanceModeRepository->findAll();    //
        
        foreach ($maintenanceModeEntity as $maintenanceModeEntita)
        {
            if (!isset($maintenanceModeSettings[$maintenanceModeEntita->getSetting()])) {continue;}
            
            $maintenanceModeEntita->setValue ( $maintenanceModeSettings[$maintenanceModeEntita->getSetting()] );
            $maintenanceModeEntita->setChangedAt();
            $entityManager->persist($maintenanceModeEntita);
        }
        
        $entityManager->flush();
    }
    
    private function ipOnWhitelist ($request, $maintenanceModeSettings)
    {
        $whitelisted_ips = json_decode ($maintenanceModeSettings['whitelisted_ips'], true); 
        
        if ( in_array($request->getClientIp(), $whitelisted_ips) ) {return true;}        
     
        return false;
    }
}
