<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class alexafiretv extends eqLogic
{
	/*     * *************************Attributs pour autoriser les onglets Affichage et Disposition****************************** */
	public static $_widgetPossibility = array('custom' => true, 'custom::layout' => true);
	
    public static function dependancy_info()
    {
        $return                  = array();
        $return['log']           = 'alexafiretv_dep';
        $return['progress_file'] = '/tmp/alexafiretv_dep';
        $adb                     = '/usr/bin/adb';
        if (is_file($adb)) {
            $return['state'] = 'ok';
        } else {
            exec('echo alexafiretv dependency not found : ' . $adb . ' > ' . log::getPathToLog('alexafiretv_log') . ' 2>&1 &');
            $return['state'] = 'nok';
        }
        return $return;
    }

    public static function dependancy_install()
    {
        log::add('alexafiretv', 'info', 'Installation des dépéndances ADB pour Fire TV');
        $resource_path = realpath(__DIR__ . '/../../3rdparty');
        passthru('/bin/bash ' . $resource_path . '/install.sh ' . $resource_path . ' > ' . log::getPathToLog('alexafiretv_dep') . ' 2>&1 &');
    }

  /**
   * Valide l'adresse d'un Fire TV (IP ou nom d'hôte) avant son usage dans une commande shell.
   * @return string|false l'adresse nettoyée, ou false si invalide.
   */
  public static function safeAdbHost($_adresseip)
    {
	$host = trim((string) $_adresseip);
	if ($host !== '' && (filter_var($host, FILTER_VALIDATE_IP) !== false || preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/', $host))) {
		return $host;
	}
	return false;
  	}

  public function lanceCmd($_name,$_cmd)
    {
	$host = self::safeAdbHost($this->getConfiguration('adresseip'));
	if ($host === false) {
		log::add('alexafiretv', 'warning', "╠═══> Adresse IP invalide pour ".$this->getName().", commande ".$_name." ignorée");
		return '';
	}
	$commande=self::prefixeRoot() . "adb -s ".escapeshellarg($host.":5555")." shell ". $_cmd;
	log::add('alexafiretv', 'debug', '╠═══> Lancement de la commande '.$_name.' => '.$commande);
	$reponse=trim(exec($commande));
	log::add('alexafiretv', 'info', " ╠═══> Récupération de ".$_name.' = '.$reponse);
	return $reponse;
  	}

  public function prefixeRoot()
    {
	return (function_exists('posix_geteuid') && posix_geteuid() === 0) ? "" : "sudo ";
  	}

    public function testFireTVConnexion($_name,$_adresseip = null)
    {
	if ($_adresseip == null) {
		log::add('alexafiretv', 'warning', "╔══════════════════════[Erreur de configuration]════════════════════════════════════════════════════════════════════════════");
		log::add('alexafiretv', 'warning', "╠═══> L'adresse IP n'a pas été précisée dans la configuration de ".$_name);
		log::add('alexafiretv', 'warning', "╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════");
		return false;
	}
	$host = self::safeAdbHost($_adresseip);
		if ($host === false) {
			log::add('alexafiretv', 'warning', "╠═══> Adresse IP invalide dans la configuration de ".$_name);
			return false;
		}
		$commande=self::prefixeRoot() . "adb devices | grep " . escapeshellarg($host) . " | cut -f2 | xargs";
	log::add('alexafiretv', 'info', " ╔══════════════════════[Test connexion à ".$_name."]════════════════════════════════════════════════════════════════════════════");
	log::add('alexafiretv', 'debug', "╠═══> Envoi de ".$commande);
	$reponse=exec($commande);
	log::add('alexafiretv', 'debug', "╠═══> Résultat [".$reponse."]");

        switch (trim($reponse)) {
		
            case 'offline':
            log::add('alexafiretv', 'info', ' ╠═══> Echec : '.$_name.' est offline');
			return $this->connectADB($_name,$_adresseip);
			break;
			
            case '':
            log::add('alexafiretv', 'info', ' ╠═══> Echec : '.$_name.' non connecté');
			return $this->connectADB($_name,$_adresseip);
			break;
			
            case 'unauthorized':
            log::add('alexafiretv', 'info', ' ╠═══> Echec : Connection non autorisée à '.$_name);
			return $this->connectADB($_name,$_adresseip);
			break;

			default: //donc = device
            log::add('alexafiretv', 'info', ' ╠═══> Succès : Tout va bien, '.$_name.' connecté');
			log::add('alexafiretv', 'info', " ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════");
			return true;			
		}
	
    }

   public function connectADB($_name,$_adresseip = null)
    {
        log::add('alexafiretv', 'info', ' ╠══════> On tente une connexion à '.$_name);
        log::add('alexafiretv', 'debug', '╠══════> Connexion au périphérique '.$_adresseip.' en cours - Envoi de adb connect '.$_adresseip);
        $host = self::safeAdbHost($_adresseip);
        if ($host === false) {
            log::add('alexafiretv', 'warning', ' ╠═══> Adresse IP invalide, connexion à '.$_name.' annulée');
            return false;
        }
        $reponse=exec(self::prefixeRoot() . "adb connect ".escapeshellarg($host));
	log::add('alexafiretv', 'debug', "╠═══> Résultat [".$reponse."]");
		switch (substr(trim($reponse),0,6)) {
		
            case 'unable':
            log::add('alexafiretv', 'info', ' ║ !!!!!! Connexion au périphérique '.$_adresseip.' : Echec '.$reponse.' !!!!!!!');
			log::add('alexafiretv', 'info', " ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════");
			return false;
			
			default: //donc = device
            log::add('alexafiretv', 'info', ' ╠═══> Succès : Tout va bien, '.$_name.' connecté');
			log::add('alexafiretv', 'info', " ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════");
			return true;			
		}
    }

    public static function cron($_eqlogic_id = null)
    {
        // Toutes les minutes, on cherche les players en lecture et on les actualise
        $dd = new Cron\CronExpression('* * * * *', new Cron\FieldFactory);
        $dependancy_info= self::dependancy_info();
        if ($dd->isDue() && $dependancy_info['state'] == 'ok') {
            $plugin = plugin::byId('alexafiretv');
            $eqLogics = eqLogic::byType($plugin->getId());
            foreach ($eqLogics as $eqLogic) {
                    $eqLogic->refresh();

            }
        }

    }

    public static function forcerDefaultCmd($_id = null)
    {
 		log::add('alexafiretv', 'debug', 'LANCE forcerDefaultCmd');
       if (!is_null($_id)) {
            $device = alexafiretv::byId($_id);
            if (is_object($device)) {
                $device->setStatus('forceUpdate', true);
                $device->save();
            }
        }
    }

    public function hasCapaorFamilyorType($thisCapa)
    {

        // Si c'est la bonne famille, on dit OK tout de suite
        $family = $this->getConfiguration('family', "");
        if ($thisCapa == $family) return true; // ajouté pour filtrer sur la famille (pour les groupes par exemple)
        // Si c'est le bon type, on dit OK tout de suite
        $type = $this->getConfiguration('type', "");
        if ($thisCapa == $type) return true; //
        $capa = $this->getConfiguration('capabilities', "");
        if (((gettype($capa) == "array" && in_array($thisCapa, $capa))) || ((gettype($capa) == "string" && strpos($capa, $thisCapa) !== false))) {
            if ($thisCapa == "REMINDERS" && $type == "A15ERDAKK5HQQG") return false;
            return true;
        } else {
            return false;
        }
    }

    public function refresh()
    { 
	
	if ($this->testFireTVConnexion($this->getName(), $this->getConfiguration('adresseip'))) {
		log::add('alexafiretv', 'info', " ╔══════════════════════[Refresh de ".$this->getName()."]════════════════════════════════════════════════════════════════════════════");
		$this->setStatus('online', true);
		$resolution  = self::RecupInfo("Résolution", 'string', "dumpsys window displays | grep init | cut -c45-53");
		$power_state = self::RecupInfo("Power", 'string', "dumpsys power -h | grep \"Display Power\" | cut -c22-");
		$encours     = self::RecupInfo("En Cours", 'string', "dumpsys window windows | grep -E 'mFocusedApp'| cut -d / -f 1 | cut -d ' ' -f 7");
		$type        = self::RecupInfo("Type", 'string', "getprop ro.build.characteristics");
		$disk_free   = self::RecupInfo("DiskFree", 'string', "dumpsys diskstats | grep Data-Free | cut -d' ' -f7");
		$disk_total  = self::RecupInfo("DiskTotal", 'string', "dumpsys diskstats | grep Data-Free | cut -d' ' -f4");
		
		if ($power_state=='ON')	$this->setStatus('on', true); else $this->setStatus('on', false);
		
	} else {
		log::add('alexafiretv', 'info', " ╔══════════════════════[Refresh de ".$this->getName()."]════════════════════════════════════════════════════════════════════════════");
		log::add('alexafiretv', 'info', ' ╠═══> Refresh annulé, '.$this->getName().' non connecté');
		$this->setStatus('online', false);
		$this->setStatus('on', false);
	}
	log::add('alexafiretv', 'info', " ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════");
	}
    
	public function RecupInfo($LogicalId, $SubType, $_shellExecDefault)
    {

	// Ici on vérifie que la commande info existe sinon on l'ajoute et on rafraichi la valeur
	try {
	$cmd = $this->getCmd(null, $LogicalId);
		if (!is_object($cmd)) {
			log::add('alexafiretv', 'debug', '╠═══> Ajout de la commande info '.$LogicalId);
			$cmd = new alexafiretvCmd();
			$cmd->setType('info');
			$cmd->setLogicalId($LogicalId);
			$cmd->setSubType($SubType);
			$cmd->setEqLogic_id($this->getId());
			if (empty($Name)) $Name = $LogicalId; // déplacé le 19/09/2020
			$cmd->setName($LogicalId);
			$cmd->setIsVisible(1);
            $cmd->setOrder("2");
			$cmd->setConfiguration('request', $_shellExecDefault);
			$cmd->setDisplay('title_disable', 0);
			$cmd->save(); // déplacé le 19/09/2020
		}
	$shellExec = $cmd->getConfiguration('request', $_shellExecDefault);
	$_resultat=$this->lanceCmd($LogicalId,$shellExec);
	if ($LogicalId == 'DiskTotal') $_resultat=round(intval($_resultat)/1000000, 1);

		log::add('alexafiretv', 'info', ' ╠═══> Enregistrement de '.$LogicalId. " --> ".$_resultat);
        $this->checkAndUpdateCmd($LogicalId, $_resultat);
	
	} catch (Exception $exc) {
		log::add('alexafiretv', 'error', __('Erreur pour ', __FILE__) . ' : ' . $exc->getMessage());
	}
	
	return $_resultat;
	}

    public function updateCmd($forceUpdate, $LogicalId, $Type, $SubType, $RunWhenRefresh, $Name, $IsVisible, $title_disable, $setDisplayicon, $infoNameArray, $setTemplate_lien, $request, $infoName, $listValue, $Order, $Test)
    {
        if ($Test) {
            try {
                $cmd = $this->getCmd(null, $LogicalId);
                if ((!is_object($cmd)) || $forceUpdate) {
					
                    if (!is_object($cmd)) $cmd = new alexafiretvCmd();
                    $cmd->setType($Type);
                    $cmd->setLogicalId($LogicalId);
                    $cmd->setSubType($SubType);
                    $cmd->setEqLogic_id($this->getId());
                    if (empty($Name)) $Name = $LogicalId; // déplacé le 19/09/2020
                    $cmd->setName($Name);
                    $cmd->setIsVisible((($IsVisible) ? 1 : 0));
                    if (!empty($setTemplate_lien)) {
                        $cmd->setTemplate("dashboard", $setTemplate_lien);
                        $cmd->setTemplate("mobile", $setTemplate_lien);
                    }
                    if (!empty($setDisplayicon)) $cmd->setDisplay('icon', '<i class="' . $setDisplayicon . '"></i>');
                    if (!empty($request)) $cmd->setConfiguration('request', $request);
                    if (!empty($infoName)) $cmd->setConfiguration('infoName', $infoName);
                    if (!empty($infoNameArray)) $cmd->setConfiguration('infoNameArray', $infoNameArray);
                    if (!empty($listValue)) $cmd->setConfiguration('listValue', $listValue);
                    $cmd->setConfiguration('RunWhenRefresh', $RunWhenRefresh);
                    $cmd->setDisplay('title_disable', $title_disable);
					$cmd->setDisplay('showNameOndashboard', !$title_disable);
                    $cmd->setOrder($Order);
                    //cas particulier
                    if (($LogicalId == 'speak') || ($LogicalId == 'announcement')) {
                        $cmd->setDisplay('message_placeholder', 'Phrase à faire lire par Alexa');
                    }
                    if (($LogicalId == 'reminder')) {
                        $cmd->setDisplay('message_placeholder', 'Texte du rappel');
                    }
                    if (($LogicalId == 'volumeinfo') || ($LogicalId == 'volume')) {
                        $cmd->setConfiguration('minValue', '0');
                        $cmd->setConfiguration('maxValue', '100');
                        $cmd->setDisplay('forceReturnLineBefore', true);
                    }
                    $cmd->save(); // déplacé le 19/09/2020
                }
            } catch (Exception $exc) {
                log::add('alexafiretv', 'error', __('Erreur pour ', __FILE__) . ' : ' . $exc->getMessage());
            }
        } else {

            $cmd = $this->getCmd(null, $LogicalId);
            if (is_object($cmd)) {
                $cmd->remove();
            }
        }
    }

    public function postSave()
    {		
		
        $F = $this->getStatus('forceUpdate'); // forceUpdate permet de recharger les commandes à valeur d'origine, mais sans supprimer/recréer les commandes
        $capa = $this->getConfiguration('capabilities', '');
        $type = $this->getConfiguration('type', '');
        if (!empty($capa)) {

            $widgetEcho = ($this->getConfiguration('devicetype') == "Echo");
            $widgetPlayer = ($this->getConfiguration('devicetype') == "Player");
            $widgetSmarthome = ($this->getConfiguration('devicetype') == "Smarthome");
            $widgetPlaylist = ($this->getConfiguration('devicetype') == "PlayList");

            $cas1 = (($this->hasCapaorFamilyorType("AUDIO_PLAYER")) && $widgetPlayer);
            $cas1bis = (($this->hasCapaorFamilyorType("AUDIO_PLAYER")) && !$widgetPlayer);
            $cas2 = (($this->hasCapaorFamilyorType("TIMERS_AND_ALARMS")) && !$widgetPlayer);
            $cas3 = (($this->hasCapaorFamilyorType("REMINDERS")) && !$widgetPlayer);
            $cas4 = (($this->hasCapaorFamilyorType("REMINDERS")) && !$widgetSmarthome);
            $cas5 = (($this->hasCapaorFamilyorType("VOLUME_SETTING")) || ($this->hasCapaorFamilyorType("SOUND_SETTINGS")));
            $cas6 = ($cas5 && (!$this->hasCapaorFamilyorType("WHA")));
            $cas7 = ((!$this->hasCapaorFamilyorType("WHA")) && ($this->getConfiguration('devicetype') != "Player") && ((!$this->hasCapaorFamilyorType("FIRE_TV")) || ($this->hasCapaorFamilyorType("A2JKHJ0PX4J3L3"))) && !$widgetSmarthome && (!$this->hasCapaorFamilyorType("AMAZONMOBILEMUSIC_ANDROID")));
            $cas8 = (($this->hasCapaorFamilyorType("turnOff")) && $widgetSmarthome);
            $cas9 = ($this->hasCapaorFamilyorType("WHA") && $widgetEcho);
            $false = false;
			
			$cas1 =true;
            self::updateCmd($F, 'home', 'action', 'other', false, 'Home', true, true, 'fas fa-home', null, null, 'input keyevent 3', null, null, 100, $cas1);            
            self::updateCmd($F, 'menu', 'action', 'other', false, 'Menu', true, true, 'fas fa-bars', null, null, 'input keyevent 1', null, null, 101, $cas1);  			self::updateCmd($F, 'search', 'action', 'other', false, 'Search', true, true, 'fas fa-search', null, null, 'input keyevent 84', null, null, 101, $cas1);            
			self::updateCmd($F, 'back', 'action', 'other', false, 'Back', true, true, 'fas fa-undo', null, null, 'input keyevent 4', null, null, 102, $cas1); 
            self::updateCmd($F, 'sleep', 'action', 'other', false, 'Sleep', true, true, 'fas fa-toggle-off', null, null, 'input keyevent 223', null, null, 103, $cas1); 
            self::updateCmd($F, 'wakeup', 'action', 'other', false, 'WakeUp', true, true, 'fas fa-toggle-on', null, null, 'input keyevent 224', null, null, 104, $cas1);
            self::updateCmd($F, 'up', 'action', 'other', false, 'Touche Haut', true, true, 'fas fa-arrow-alt-circle-up', null, null, 'input keyevent 19', null, null, 110, $cas1);
            self::updateCmd($F, 'down', 'action', 'other', false, 'Touche Bas', true, true, 'fas fa-arrow-alt-circle-down', null, null, 'input keyevent 20', null, null, 111, $cas1);   
            self::updateCmd($F, 'left', 'action', 'other', false, 'Touche Gauche', true, true, 'fas fa-arrow-alt-circle-left', null, null, 'input keyevent 21', null, null, 112, $cas1);	
            self::updateCmd($F, 'right', 'action', 'other', false, 'Touche Droite', true, true, 'fas fa-arrow-alt-circle-right', null, null, 'input keyevent 22', null, null, 113, $cas1);			
            self::updateCmd($F, 'enter', 'action', 'other', false, 'Touche Entrée', true, true, 'fas fa-sign-in-alt', null, null, 'input keyevent 66', null, null, 114, $cas1);			
			self::updateCmd($F, 'play', 'action', 'other', false, 'Play', true, true, 'fas fa-play', null, null, 'media dispatch play', null, null, 120, $cas1);
			self::updateCmd($F, 'pause', 'action', 'other', false, 'Pause', true, true, 'fas fa-pause', null, null, 'media dispatch pause', null, null, 121, $cas1);
            self::updateCmd($F, 'playpause', 'action', 'other', false, 'Play-Pause', true, true, 'fas fa-play-circle', null, null, 'media dispatch play-pause', null, null, 122, $cas1);
            self::updateCmd($F, 'next', 'action', 'other', false, 'Next', true, true, 'fas fa-step-forward', null, null, 'media dispatch next', null, null, 123, $cas1);
            self::updateCmd($F, 'previous', 'action', 'other', false, 'Previous', true, true, 'fas fa-step-backward', null, null, 'media dispatch previous', null, null, 124, $cas1);
            self::updateCmd($F, 'cmd_power', 'action', 'other', false, 'On', true, true, 'fas fa-power-off', null, null, 'input keyevent 26', null, null, 130, $cas1);             
			self::updateCmd($F, 'netflix', 'action', 'other', false, 'Netflix', true, true, 'fas fa-share-square', null, null, 'am start com.netflix.ninja/.MainActivity', null, null, 150, $cas1); 

                 //Commande Refresh
				$cmd = $this->getCmd(null, 'refresh');
                if (!is_object($cmd)) {
                    log::add('alexafiretv', 'info', 'ajout commande Refresh');
                    $cmd = new alexafiretvCmd();
					$cmd->setLogicalId('refresh');
					$cmd->setIsVisible(1);
					$cmd->setOrder("1");
					$cmd->setDisplay('icon', '<i class="fas fa-sync"></i>');
					$cmd->setName(__('Refresh', __FILE__));				
                    $cmd->setType('action');
                    $cmd->setSubType('other');
                    $cmd->setEqLogic_id($this->getId());
                    $cmd->save();	
				}					

        } else {
            log::add('alexafiretv', 'warning', 'Pas de capacité détectée sur ' . $this->getName() . ' , assurez-vous que le démon est OK');
        }

        $this->setStatus('forceUpdate', false); //dans tous les cas, on repasse forceUpdate à false

    }

    public function preUpdate()
    {

    }

    public function preRemove()
    {
        if ($this->getConfiguration('devicetype') == "Player") { // Si c'est un type Player, il faut supprimer le Device Playlist
            $device_playlist = str_replace("_player", "", $this->getConfiguration('serial')) . "_playlist"; //Nom du device de la playlist
            $eq = eqLogic::byLogicalId($device_playlist, 'alexafiretv');
            if (is_object($eq)) $eq->remove();
        }
    }

    public function preSave()
    {
    }

    // https://github.com/NextDom/NextDom/wiki/Ajout-d%27un-template-a-votre-plugin
    // https://jeedom.github.io/documentation/dev/fr_FR/widget_plugin

    public function toHtml($_version = 'dashboard')
    {
        $replace = $this->preToHtml($_version);
        $typeWidget = "alexafiretv";

        $typeWidget = $this->getLogicalId();
        if ($typeWidget != "alexafiretv_playlist") return parent::toHtml($_version);
        if (!is_array($replace)) {
            return $replace;
        }
        $version = jeedom::versionAlias($_version);
        if ($this->getDisplay('hideOn' . $version) == 1) {
            return '';
        }
        foreach ($this->getCmd('info') as $cmd) {
            $replace['#' . $cmd->getLogicalId() . '_history#'] = '';
            $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
            $replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
            $replace['#' . $cmd->getLogicalId() . '_collect#'] = $cmd->getCollectDate();
            if ($cmd->getLogicalId() == 'encours') {
                $replace['#thumbnail#'] = $cmd->getDisplay('icon');
            }
            if ($cmd->getIsHistorized() == 1) {
                $replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
            }
        }
        $replace['#height#'] = '800';
        if ($typeWidget == "alexafiretv_playlist") {
            if ("#playlistName#" != "") {
                $replace['#name_display#'] = '#playlistName#';
            }
        }
        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, $typeWidget, 'alexafiretv')));
    }
}

class alexafiretvCmd extends cmd
{

    public function postSave()
    {

    }

    public function preSave()
    {

        if ($this->getLogicalId() == 'refresh') {
            return;
        }
    }

  public function lanceCommande($_name,$_cmd)
    {

	$reponse = '';
		$host = alexafiretv::safeAdbHost($this->getEqLogic()->getConfiguration('adresseip'));
		if ($host !== false && $this->getEqLogic()->testFireTVConnexion($this->getEqLogic()->getName(), $host)) {
	log::add('alexafiretv', 'info', " ╔══════════════════════[Lance Commande Action ".$_name."]════════════════════════════════════════════════════════════════════════════");
	$commande=self::prefixeRoot2() . "adb -s ".escapeshellarg($host.":5555")." shell ". $_cmd ;
	log::add('alexafiretv', 'debug', '╠═══> Envoi de '.$commande);
	$reponse=exec($commande, $output, $intout);
	log::add('alexafiretv', 'info', " ╠═══> Réponse à la commande ".$_name.' = '.$intout.' (0=OK)');
		log::add('alexafiretv', 'info', " ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════");

	}
	
	return $reponse;
  	}
	
  public function prefixeRoot2()
    {
	return (function_exists('posix_geteuid') && posix_geteuid() === 0) ? "" : "sudo ";
  	}
	
    public function execute($_options = null)
    {
		
        if ($this->getLogicalId() == 'refresh') {
            $this->getEqLogic()->refresh();
            return;
        }
        $request = $this->buildRequest($_options);

        return true;
    }

    private function buildRequest($_options = array())
    {
        if ($this->getType() != 'action') return escapeshellcmd($this->getConfiguration('request'));
        $cmdANDarg = explode('?', escapeshellcmd($this->getConfiguration('request')), 2);
        if (count($cmdANDarg) > 1) {
		list($command, $arguments) = $cmdANDarg;}
        else {
            $command = escapeshellcmd($this->getConfiguration('request'));
            $arguments = "";
        }
				
		$_resultat=$this->lanceCommande("test",$command);

		return true;
    }

    public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '')
    {
        if ($_version != 'scenario') return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
        list($command, $arguments) = explode('?', $this->getConfiguration('request'), 2);
        if (($command == 'speak') || ($command == 'announcement'))
            return getTemplate('core', 'scenario', 'cmd.speak.volume', 'alexafiretv');
        if ($command == 'reminder')
            return getTemplate('core', 'scenario', 'cmd.reminder', 'alexafiretv');
        if ($command == 'deleteReminder')
            return getTemplate('core', 'scenario', 'cmd.deletereminder', 'alexafiretv');
        if ($command == 'deleteallalarms')
            return getTemplate('core', 'scenario', 'cmd.deleteallalarms', 'alexafiretv');
        if ($command == 'command' && strpos($arguments, '#select#'))
            return getTemplate('core', 'scenario', 'cmd.command', 'alexafiretv');
        if ($command == 'alarm')
            return getTemplate('core', 'scenario', 'cmd.alarm', 'alexafiretv');
        return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
    }
}
