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
            //log::add('alexafiretv', 'debug', 'CRON1 ');
            $plugin = plugin::byId('alexafiretv');
            $eqLogics = eqLogic::byType($plugin->getId());
            //log::add('alexafiretv', 'debug', 'CRON1_1 de ' . $eqLogic->getName());
            foreach ($eqLogics as $eqLogic) {
            //log::add('alexafiretv', 'debug', 'CRON1_2 de ' . $eqLogic->getName());
                    //log::add('alexafiretv', 'debug', 'Refresh automatique (CRON) de ' . $eqLogic->getName());
                    $eqLogic->refresh();

            }
        }



        //log::add('alexafiretv', 'debug', '---------------------------------------------FIN CRON------------------------');
    }

    public static function checkAuth()
    {
		log::add('alexafiretv', 'debug', 'LANCE checkAuth');
        $result = file_get_contents("http://" . config::byKey('internalAddr') . ":3456/checkAuth");
        $resultjson = json_decode($result, true);
        $value = $resultjson['authenticated'];
        if ($value == 1)
            log::add('alexafiretv', 'debug', 'Résultat du checkAuth  OK (' . $value . ')');
        else {
            log::add('alexafiretv', 'debug', 'Résultat du checkAuth NOK (' . $value . ') ==> Relance Serveur');
            self::restartServeurPHP();
            //message::add('alexafiretv', '(Beta Alexa-api) Authentification Amazon revalidée, tout va bien');
        }
    }

    public static function restartServeurPHP()
    {
        $json = file_get_contents("http://" . config::byKey('internalAddr') . ":3456/restart");
        sleep(2);
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


    public static function createNewDevice($deviceName, $deviceSerial)
    {
 		log::add('alexafiretv', 'debug', 'LANCE createNewDevice');
		//$defaultRoom = intval(config::byKey('defaultParentObject', "alexafiretv", '', true));
        event::add('jeedom::alert', array('level' => 'success', 'page' => 'alexafiretv', 'message' => __('Ajout de "' . $deviceName . '"', __FILE__),));
        //log::add('alexaapi_scan', 'debug', 'newDevice');
        $newDevice = new alexafiretv();
        $newDevice->setName($deviceName);
        $newDevice->setLogicalId($deviceSerial);
        $newDevice->setEqType_name('alexafiretv');
        $newDevice->setIsVisible(1);
        //if ($defaultRoom) $newDevice->setObject_id($defaultRoom);
        // JUSTE pour SIGALOU pour aider au dev
        //if (substr($deviceName, 0, 7) == "Piscine")
        //    $newDevice->setObject_id('15');
        $newDevice->setDisplay('height', '500');
        $newDevice->setConfiguration('device', $deviceName);
        $newDevice->setConfiguration('serial', $deviceSerial);
        $newDevice->setIsEnable(1);
        return $newDevice;
    }

    public function hasCapaorFamilyorType($thisCapa)
    {
 		//log::add('alexafiretv', 'debug', 'LANCE hasCapaorFamilyorType');

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

    public function sortByPRECEDENTAVIRER($field, &$array, $direction = 'asc')
    {
        usort($array, create_function('$a, $b', '
		$a = $a["' . $field . '"];
		$b = $b["' . $field . '"];
		if ($a == $b) return 0;
		$direction = strtolower(trim($direction));
		return ($a ' . ($direction == 'desc' ? '>' : '<') . ' $b) ? -1 : 1;
    	'));
        return true;
    }

    /*
    public function sortBy($field, &$array) {
        usort($array, create_function('$a, $b', '
        $a = $a["' . $field . '"];
        $b = $b["' . $field . '"];
        if ($a == $b) return 0;
        return ($a < $b) ? -1 : 1;
        '));
        return true;
    }*/


    public function refresh()
    { 
 		//log::add('alexafiretv', 'debug', 'LANCE refresh');
	
	if ($this->testFireTVConnexion($this->getName(), $this->getConfiguration('adresseip'))) {
		log::add('alexafiretv', 'info', " ╔══════════════════════[Refresh de ".$this->getName()."]════════════════════════════════════════════════════════════════════════════");
		$this->setStatus('online', true);
		$resolution  = self::RecupInfo("Résolution", 'string', "dumpsys window displays | grep init | cut -c45-53");
		$power_state = self::RecupInfo("Power", 'string', "dumpsys power -h | grep \"Display Power\" | cut -c22-");
		$encours     = self::RecupInfo("En Cours", 'string', "dumpsys window windows | grep -E 'mFocusedApp'| cut -d / -f 1 | cut -d ' ' -f 7");
		$type        = self::RecupInfo("Type", 'string', "getprop ro.build.characteristics");
		$disk_free   = self::RecupInfo("DiskFree", 'string', "dumpsys diskstats | grep Data-Free | cut -d' ' -f7");
		//$disk_total  = self::RecupInfo("DiskTotal", 'string', round($this->lanceCmd("DiskTotal","shell dumpsys diskstats | grep Data-Free | cut -d' ' -f4")/1000000, 1));
		$disk_total  = self::RecupInfo("DiskTotal", 'string', "dumpsys diskstats | grep Data-Free | cut -d' ' -f4");
		
		if ($power_state=='ON')	$this->setStatus('on', true); else $this->setStatus('on', false);
		
		//$title = substr($this->lanceCmd("Titre","shell dumpsys bluetooth_manager | grep MediaPlayerInfo | grep .$encours. |cut -d')' -f3 | cut -d, -f1 | grep -v null | sed 's/^\ *//g'"), 0);
		//$name        = self::RecupInfo("Name", substr($this->lanceCmd("Name","shell getprop ro.product.model"), 0, -1));
		//$volume = substr($this->lanceCmd("Volume","shell media volume --stream 3 --get | grep volume |grep is | cut -d\ -f4"), 0, -1);
		//$play_state  = substr($this->lanceCmd("Status","shell dumpsys bluetooth_manager | grep mCurrentPlayState | cut -d,  -f1 | cut -c43-"), 0, -1);
		//$lancePause        = substr($this->lanceCmd("Pause","shell media dispatch pause"), 0, -1);
		//$lancePause        = substr($this->lanceCmd("Play","shell media dispatch play"), 0, -1);
	} else {
		log::add('alexafiretv', 'info', " ╔══════════════════════[Refresh de ".$this->getName()."]════════════════════════════════════════════════════════════════════════════");
		//log::add('alexafiretv', 'debug', "résultat test connexion: " .$resultatTestConnexion );
		log::add('alexafiretv', 'info', ' ╠═══> Refresh annulé, '.$this->getName().' non connecté');
		$this->setStatus('online', false);
		$this->setStatus('on', false);
	}
	log::add('alexafiretv', 'info', " ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════");
      //log::add('alexafiretv', 'debug', "resolution: " .$resolution );
	}
    
	public function RecupInfo($LogicalId, $SubType, $_shellExecDefault)
    {
		

	//log::add('alexafiretv', 'debug', '╠═══> Traitement '.$LogicalId.' => '.$_resultat);
	// Ici on vérifie que la commande info existe sinon on l'ajoute et on rafraichi la valeur
	try {
	$cmd = $this->getCmd(null, $LogicalId);
//	if ((!is_object($cmd)) || $forceUpdate) {
		if (!is_object($cmd)) {
			log::add('alexafiretv', 'debug', '╠═══> Ajout de la commande info '.$LogicalId);
	//		if (!is_object($cmd)) $cmd = new alexafiretvCmd();
			$cmd = new alexafiretvCmd();
			$cmd->setType('info');
			$cmd->setLogicalId($LogicalId);
			$cmd->setSubType($SubType);
			$cmd->setEqLogic_id($this->getId());
			if (empty($Name)) $Name = $LogicalId; // déplacé le 19/09/2020
			$cmd->setName($LogicalId);
			$cmd->setIsVisible(1);
            $cmd->setOrder("2");
	//		if (!empty($setDisplayicon)) $cmd->setDisplay('icon', '<i class="' . $setDisplayicon . '"></i>');
			$cmd->setConfiguration('request', $_shellExecDefault);
			$cmd->setDisplay('title_disable', 0);
			//$cmd->setOrder($Order);
			$cmd->save(); // déplacé le 19/09/2020
		}
	$shellExec = $cmd->getConfiguration('request', $_shellExecDefault);
	$_resultat=$this->lanceCmd($LogicalId,$shellExec);
	if ($LogicalId == 'DiskTotal') $_resultat=round(intval($_resultat)/1000000, 1);
		
		
		//$cmd->setValue($_resultat);
		log::add('alexafiretv', 'info', ' ╠═══> Enregistrement de '.$LogicalId. " --> ".$_resultat);
        $this->checkAndUpdateCmd($LogicalId, $_resultat);
	
	} catch (Exception $exc) {
		log::add('alexafiretv', 'error', __('Erreur pour ', __FILE__) . ' : ' . $exc->getMessage());
	}
	
	return $_resultat;
	}

    public function updateCmd($forceUpdate, $LogicalId, $Type, $SubType, $RunWhenRefresh, $Name, $IsVisible, $title_disable, $setDisplayicon, $infoNameArray, $setTemplate_lien, $request, $infoName, $listValue, $Order, $Test)
    {
 	//	log::add('alexafiretv', 'debug', 'LANCE updateCmd');
		//self::afficheToutesCommandes("updateCmd");
        if ($Test) {
            //log::add('alexafiretv', 'info', 'ajout commande FORCAGE de ' . $LogicalId);
            try {
                $cmd = $this->getCmd(null, $LogicalId);
                if ((!is_object($cmd)) || $forceUpdate) {
					
                    //log::add('alexafiretv', 'info', 'ajout commande FORCAGE forceUpdate :' . $forceUpdate);
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
                        //$cmd->setDisplay('title_placeholder', 'Options');
                        $cmd->setDisplay('message_placeholder', 'Phrase à faire lire par Alexa');
                    }
                    if (($LogicalId == 'reminder')) {
                        //$cmd->setDisplay('title_placeholder', 'Options');
                        $cmd->setDisplay('message_placeholder', 'Texte du rappel');
                    }
                    if (($LogicalId == 'volumeinfo') || ($LogicalId == 'volume')) {
                        $cmd->setConfiguration('minValue', '0');
                        $cmd->setConfiguration('maxValue', '100');
                        $cmd->setDisplay('forceReturnLineBefore', true);
                    }
                    $cmd->save(); // déplacé le 19/09/2020
                    //log::add('alexafiretv', 'info', 'Enregistre Logical ID :' . $cmd->getLogicalId());
                }
            } catch (Exception $exc) {
                log::add('alexafiretv', 'error', __('Erreur pour ', __FILE__) . ' : ' . $exc->getMessage());
            }
        } else {
            //log::add('alexafiretv', 'debug', 'PAS de **'.$LogicalId.'*********************************');

            $cmd = $this->getCmd(null, $LogicalId);
            if (is_object($cmd)) {
                $cmd->remove();
            }
        }
    }

    public function afficheToutesCommandes($Position)
    {			foreach ($this->getCmd('action') as $cmd) {
                    log::add('alexafiretv', 'info', $Position.'--cmd:'.$cmd->getLogicalId()."/".$cmd->getName());
				}
	}
	

    public function postSave()
    {		
		//self::afficheToutesCommandes("postSave");
		
        //log::add('alexafiretv', 'debug', '-------------------------------postSave '.$this->getName().'***********************************');
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
			
			
			//self::updateCmd($F, 'volume1', 'action', 'other', false, 'Volume1', true, true, 'fas fa-volume-up', null, null, 'media volume --show --stream 1 --set 1', null, null, 105, $cas1);
            //self::updateCmd($F, 'volume11', 'action', 'other', false, 'Volume11', true, true, 'fas fa-volume-up', null, null, 'media volume --show --stream 3 --set 11', null, null, 106, $cas1);
			
			
			
			
			
			/*
            // Volume on traite en premier car c'est fonction de WHA
            if ($cas6) self::updateCmd($F, 'volume', 'action', 'slider', false, 'Volume', true, true, 'fas fa-volume-up', null, 'alexafiretv::volume', 'volume?value=#slider#', null, null, 27, $cas6);
            else       self::updateCmd($F, 'volume', 'action', 'slider', false, 'Volume', false, true, 'fas fa-volume-up', null, 'alexafiretv::volume', 'volume?value=#slider#', null, null, 27, $cas9);

            //Anciennes functions a supprimer si elles existent encore
            self::updateCmd($F, 'musicalalarmmusicentity', 'action', 'other', true, 'musicalalarmmusicentity', false, false, null, null, null, 'musicalalarmmusicentity?position=1', 'Musical Alarm Music', null, 1, $false);
            self::updateCmd($F, 'whennextmusicalalarm', 'action', 'other', true, 'Next Musical Alarm When', false, false, 'fas fa-bell', null, null, 'whennextmusicalalarm?position=1', 'Next Musical Alarm Hour', null, 1, $false);
            self::updateCmd($F, 'whennextreminder', 'action', 'other', true, 'Next Reminder When', false, false, null, null, null, 'whennextreminder?position=1', 'Next Reminder Hour', null, 33, $false);
            self::updateCmd($F, 'whennextreminderlabel', 'action', 'other', true, 'whennextreminderlabel', false, false, null, null, null, 'whennextreminderlabel?position=1', 'Reminder Label', null, 34, $false);

            self::updateCmd($F, 'interactioninfo', 'info', 'string', false, 'Dernier dialogue avec Alexa', true, false, null, null, 'alexafiretv::interaction', null, null, null, 2, $cas7);
            self::updateCmd($F, 'bluetoothDevice', 'info', 'string', false, 'Est connecté en Bluetooth', true, false, null, null, 'alexafiretv::interaction', null, null, null, 2, $cas7);
            self::updateCmd($F, 'updateallalarms', 'action', 'other', true, 'UpdateAllAlarms', false, false, null, ["musicalalarmmusicentityinfo", "whennextalarminfo", "whennextmusicalalarminfo", "whennextreminderinfo", "whennexttimerinfo", "whennextreminderlabelinfo"], null, 'updateallalarms', null, null, 2, $cas2);
            self::updateCmd($F, 'deleteReminder', 'action', 'message', false, 'Supprimer un rappel', false, false, 'maison-poubelle', null, null, 'deleteReminder?id=#id#', null, null, 2, $cas3);
            self::updateCmd($F, 'subText2', 'info', 'string', false, null, true, false, null, null, 'alexafiretv::subText2', null, null, null, 2, $cas1);
            self::updateCmd($F, 'subText1', 'info', 'string', false, null, true, false, null, null, 'alexafiretv::title', null, null, null, 4, $cas1);
            self::updateCmd($F, 'url', 'info', 'string', false, null, true, false, null, null, 'alexafiretv::image', null, null, null, 5, $cas1);
            self::updateCmd($F, 'title', 'info', 'string', false, null, true, false, null, null, 'alexafiretv::title', null, null, null, 9, $cas1);
            self::updateCmd($F, 'previous', 'action', 'other', false, 'Previous', true, true, 'fas fa-step-backward', null, null, 'command?command=previous', null, null, 16, $cas1);
            self::updateCmd($F, 'next', 'action', 'other', false, 'Next', true, true, 'fas fa-step-forward', null, null, 'command?command=next', null, null, 19, $cas1);
            self::updateCmd($F, 'multiplenext', 'action', 'other', false, 'Multiple Next', true, true, 'fas fa-step-forward', null, null, 'multiplenext?text=#message#', null, null, 19, $cas1);
            self::updateCmd($F, 'providerName', 'info', 'string', false, 'Fournisseur de musique :', true, true, 'loisir-musical7', null, null, null, null, null, 20, $cas1);
            self::updateCmd($F, 'contentId', 'info', 'string', false, 'Amazon Music Id', false, true, 'loisir-musical7', null, null, null, null, null, 21, $cas1);
            self::updateCmd($F, 'routine', 'action', 'select', false, 'Lancer une routine', true, false, null, null, 'alexafiretv::list', 'routine?routine=#select#', null, 'Lancer Refresh|Lancer Refresh', 21, $cas3);
            self::updateCmd($F, 'playList', 'action', 'select', false, 'Ecouter une playlist', true, false, null, null, 'alexafiretv::list', 'playlist?playlist=#select#', null, 'Lancer Refresh|Lancer Refresh', 24, $cas1);
            self::updateCmd($F, 'radio', 'action', 'select', false, 'Ecouter une radio', true, false, null, null, 'alexafiretv::list', 'radio?station=#select#', null, 's2960|Nostalgie;s6617|RTL;s6566|Europe1', 25, $cas1);
            self::updateCmd($F, 'playMusicTrack', 'action', 'select', false, 'Ecouter une piste musicale', true, false, null, null, 'alexafiretv::list', 'playmusictrack?trackId=#select#', null, '53bfa26d-f24c-4b13-97a8-8c3debdf06f0|Piste1;7b12ee4f-5a69-4390-ad07-00618f32f110|Piste2', 26, $cas1);

            self::updateCmd($F, '0', 'action', 'other', false, '0', true, true, null, null, null, 'volume?value=0', null, null, 1, $cas9);
            self::updateCmd($F, 'volume20', 'action', 'other', false, '20', true, true, null, null, null, 'volume?value=20', null, null, 2, $cas9);
            self::updateCmd($F, 'volume40', 'action', 'other', false, '40', true, true, null, null, null, 'volume?value=40', null, null, 3, $cas9);
            self::updateCmd($F, 'volume60', 'action', 'other', false, '60', true, true, null, null, null, 'volume?value=60', null, null, 4, $cas9);
            self::updateCmd($F, 'volume80', 'action', 'other', false, '80', true, true, null, null, null, 'volume?value=80', null, null, 5, $cas9);
            self::updateCmd($F, 'volume100', 'action', 'other', false, '100', true, true, null, null, null, 'volume?value=100', null, null, 6, $cas9);
            self::updateCmd($F, 'volumeinfo', 'info', 'string', false, 'Volume Info', false, false, 'fas fa-volume-up', null, null, null, null, null, 28, $cas6);
            self::updateCmd($F, 'whennextalarminfo', 'info', 'string', false, 'Prochaine Alarme', true, false, null, null, 'alexafiretv::alarm', null, null, null, 29, $cas2);
            self::updateCmd($F, 'whennextmusicalalarminfo', 'info', 'string', false, 'Prochaine Alarme Musicale', true, false, null, null, 'alexafiretv::alarmmusical', null, null, null, 30, $cas2);
            self::updateCmd($F, 'musicalalarmmusicentityinfo', 'info', 'string', false, 'Musical Alarm Music', true, false, 'loisir-musical7', null, 'alexafiretv::alarmmusicalmusic', null, null, null, 31, $cas2);
            self::updateCmd($F, 'whennextreminderinfo', 'info', 'string', false, 'Prochain Rappel', true, false, null, null, 'alexafiretv::reminder', null, null, null, 32, $cas3);
            self::updateCmd($F, 'whennexttimerinfo', 'info', 'string', false, 'Prochain Minuteur', true, false, null, null, 'alexafiretv::timer', null, null, null, 32, $cas3);
            self::updateCmd($F, 'whennextreminderlabelinfo', 'info', 'string', false, 'Reminder Label', true, false, 'loisir-musical7', null, 'alexafiretv::alarmmusicalmusic', null, null, null, 35, $cas2);
            self::updateCmd($F, 'alarm', 'action', 'select', false, 'Lancer une alarme', true, false, null, null, 'alexafiretv::list', 'alarm?when=#when#&recurring=#recurring#&sound=#sound#', null, 'system_alerts_melodic_01|Alarme simple;system_alerts_melodic_01|Timer simple;system_alerts_melodic_02|A la dérive;system_alerts_atonal_02|Métallique;system_alerts_melodic_05|Clarté;system_alerts_repetitive_04|Comptoir;system_alerts_melodic_03|Focus;system_alerts_melodic_06|Lueur;system_alerts_repetitive_01|Table de chevet;system_alerts_melodic_07|Vif;system_alerts_soothing_05|Orque;system_alerts_atonal_03|Lumière du porche;system_alerts_rhythmic_02|Pulsar;system_alerts_musical_02|Pluvieux;system_alerts_alarming_03|Ondes carrées', 36, $cas3);
            //self::updateCmd ($F, 'rwd', 'action', 'other', false, 'Rwd', true, true, 'fas fa-fast-backard', null, null, 'command?command=rwd', null, null, 80, $cas1);

            //self::updateCmd ($F, 'fwd', 'action', 'other', false, 'Fwd', true, true, 'fas fa-step-forward', null, null, 'command?command=fwd', null, null, 20, $cas1);
            //self::updateCmd ($F, 'repeat', 'action', 'other', false, 'Repeat', true, true, 'fas fa-refresh', null, null, 'command?command=repeat', null, null, 25, $cas1);
            //self::updateCmd ($F, 'shuffle', 'action', 'other', false, 'Shuffle', true, true, 'fas fa-random', null, null, 'command?command=shuffle', null, null, 26, $cas1);
            self::updateCmd($F, 'playlistName', 'info', 'string', false, null, true, true, null, null, null, null, null, null, 79, $widgetPlaylist);
            //self::updateCmd ($F, 'playlistName', 'info', 'string', false, null, false, true, null, null, null, null, null, null, 2, $widgetPlayer);
            //self::updateCmd ($F, 'songName', 'info', 'string', false, null, true, false, null, null, null, null, null, null, 79, ($widgetPlaylist || $widgetPlayer));
            self::updateCmd($F, 'playlisthtml', 'info', 'string', false, null, true, true, null, null, null, null, null, null, 79, $widgetPlaylist);
            self::updateCmd($F, 'turnOn', 'action', 'other', false, 'turnOn', true, true, "fas fa-circle", null, null, 'SmarthomeCommand?command=turnOn', null, null, 79, $cas8);
            self::updateCmd($F, 'turnOff', 'action', 'other', false, 'turnOff', true, true, "far fa-circle", null, null, 'SmarthomeCommand?command=turnOff', null, null, 79, $cas8);

            self::updateCmd($F, 'command', 'action', 'message', false, 'Command', false, true, "fas fa-play-circle", null, null, 'command?command=#select#', null, null, 79, $cas1);
            self::updateCmd($F, 'speak', 'action', 'message', false, 'Faire parler Alexa', true, false, null, null, 'alexafiretv::message', 'speak?text=#message#&volume=#volume#', null, null, 40, $cas1bis);
            self::updateCmd($F, 'speaklegacy', 'action', 'message', false, 'Faire parler Alexa (legacy)', false, false, null, null, 'alexafiretv::message', 'speak?text=#message#&volume=#volume#&legacy=1', null, null, 43, $cas1bis);
            self::updateCmd($F, 'announcement', 'action', 'message', false, 'Lancer une annonce', true, true, null, null, 'alexafiretv::message', 'speak?text=#message#&volume=#volume#&jingle=1', null, null, 44, $cas1bis);
            self::updateCmd($F, 'speakssml', 'action', 'message', false, 'Faire parler Alexa en SSML', false, true, null, null, 'alexafiretv::message', 'speak?text=#message#&volume=#volume#&ssml=1', null, null, 42, $cas1bis);
            self::updateCmd($F, 'mediaLength', 'info', 'string', false, null, false, false, null, null, null, null, null, null, 79, $cas1);
            self::updateCmd($F, 'mediaProgress', 'info', 'string', false, null, false, false, null, null, null, null, null, null, 79, $cas1);
            self::updateCmd($F, 'state', 'info', 'string', false, null, true, false, null, null, 'alexafiretv::state', null, null, null, 79, $cas1);
            //self::updateCmd ($F, 'playlistName', 'info', 'string', false, null, false, false, null, null, null, null, null, null, 2, $cas1);
            self::updateCmd($F, 'nextState', 'info', 'string', false, null, false, true, null, null, null, null, null, null, 79, $cas1);
            self::updateCmd($F, 'previousState', 'info', 'string', false, null, false, true, null, null, null, null, null, null, 79, $cas1);
            self::updateCmd($F, 'playPauseState', 'info', 'string', false, null, false, true, null, null, null, null, null, null, 79, $cas1);
            //self::updateCmd ($F, 'loopMode', 'info', 'string', false, null, true, false, null, null, null, null, null, null, 79, $cas1);
            //self::updateCmd ($F, 'playBackOrder', 'info', 'string', false, null, true, false, null, null, null, null, null, null, 79, $cas1);
            //self::updateCmd ($F, 'alarm', 'action', 'message', false, 'Alarm', false, true, 'fas fa-bell', null, null, 'alarm?when=#when#&recurring=#recurring#&sound=#sound#', null, null, 79, $cas2);
            self::updateCmd($F, 'deleteallalarms', 'action', 'message', false, 'Delete All Alarms', false, false, 'maison-poubelle', null, null, 'deleteallalarms?type=#type#&status=#status#', null, null, 79, $cas2);
            if ($type == "A15ERDAKK5HQQG") {
                log::add('alexafiretv', 'warning', '****Rencontre du type A15ERDAKK5HQQG = Sonos Première Génération sur : ' . $this->getName());
                log::add('alexafiretv', 'warning', '****On ne crée pas les commandes REMINDERS dessus car bug!');
            }
            self::updateCmd($F, 'reminder', 'action', 'message', false, 'Envoyer un rappel', true, false, null, null, 'alexafiretv::message', 'reminder?text=#message#&when=#when#&recurring=#recurring#', null, null, 79, $cas3);

            self::updateCmd($F, 'onLine', 'info', 'binary', false, "En ligne", false, true, null, null, null, null, null, null, 99, true); //ajouté aout 2020
*/

/*				
			     foreach ($this->getCmd('action') as $cmd) {
                    log::add('alexafiretv', 'info', 'command:'.$cmd->getName());
					
					if (!is_object($cmd)) {
                    log::add('alexafiretv', 'info', 'existe pas:'.$cmd->getName());
					} else {
                    log::add('alexafiretv', 'info', 'existe:'.$cmd->getName());
					}
				 }			
*/								
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

 
          //  }
        } else {
            log::add('alexafiretv', 'warning', 'Pas de capacité détectée sur ' . $this->getName() . ' , assurez-vous que le démon est OK');
        }

        //event::add('jeedom::alert', array('level' => 'success', 'page' => 'alexafiretv', 'message' => __('Mise à jour de "' . $this->getName() . '"', __FILE__),));
        //$this->refresh(); on enlève pour voir si ça pose pas un souci


        $this->setStatus('forceUpdate', false); //dans tous les cas, on repasse forceUpdate à false
		//self::afficheToutesCommandes("postSave2");

    }



    public function preUpdate()
    {
        //log::add('alexafiretv', 'debug', '-------------------------------preUpdate '.$this->getName().'***********************************');
				//self::afficheToutesCommandes("preUpdate");

    }

    public function preRemove()
    {
        if ($this->getConfiguration('devicetype') == "Player") { // Si c'est un type Player, il faut supprimer le Device Playlist
            $device_playlist = str_replace("_player", "", $this->getConfiguration('serial')) . "_playlist"; //Nom du device de la playlist
            $eq = eqLogic::byLogicalId($device_playlist, 'alexafiretv');
            if (is_object($eq)) $eq->remove();
        }
        //log::add('alexafiretv', 'debug', '-------------------------------preRemove '.$this->getName().'***********************************');
    }

    public function preSave()
    {
        //log::add('alexafiretv', 'debug', '-------------------------------preSave '.$this->getName().'***********************************');
//self::afficheToutesCommandes("preSave");
    }

    // https://github.com/NextDom/NextDom/wiki/Ajout-d%27un-template-a-votre-plugin
    // https://jeedom.github.io/documentation/dev/fr_FR/widget_plugin

    public function toHtml($_version = 'dashboard')
    {
        $replace = $this->preToHtml($_version);
        //log::add('alexafiretv_widget','debug','************Début génération Widget de '.$replace['#logicalId#']);
        $typeWidget = "alexafiretv";

        $typeWidget = $this->getLogicalId();
        //if ((substr($replace['#logicalId#'], -7)) == "_player") $typeWidget = "alexafiretv_player";
        //if ((substr($replace['#logicalId#'], -9)) == "_playlist") $typeWidget = "alexafiretv_playlist";
        if ($typeWidget != "alexafiretv_playlist") return parent::toHtml($_version);
        //log::add('alexafiretv_widget','debug',$typeWidget.'************Début génération Widget de '.$replace['#name#']);
        if (!is_array($replace)) {
            return $replace;
        }
        $version = jeedom::versionAlias($_version);
        if ($this->getDisplay('hideOn' . $version) == 1) {
            return '';
        }
        foreach ($this->getCmd('info') as $cmd) {
            //log::add('alexafiretv_widget','debug',$typeWidget.'dans boucle génération Widget');
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
        //log::add('alexafiretv_widget','debug',$typeWidget.'***************************************************************************Fin génération Widget');
        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, $typeWidget, 'alexafiretv')));
    }
}

class alexafiretvCmd extends cmd
{

    public function dontRemoveCmd()
    {
		 //		log::add('alexafiretv', 'debug', 'LANCE dontRemoveCmd cmd');

        if ($this->getLogicalId() == 'refresh') {
            return true;
        }
        return false;
    }

    public function postSave()
    {
        //log::add('alexafiretv', 'debug', '**********************postSave '.$this->getName().'***********************************'.$this->getLogicalId());

    }

    public function preSave()
    {
     //   log::add('alexafiretv', 'debug', '**********************preSave '.$this->getName().'***********************************'.$this->getLogicalId());

        if ($this->getLogicalId() == 'refresh') {
            return;
        }
		/*
        if ($this->getType() == 'action') {
        log::add('alexafiretv', 'debug', '!!!!!!!!!!!!!!!!!ICI '.$this->getName().'***********************************'.$this->getLogicalId());
            $eqLogic = $this->getEqLogic();
            //$this->setConfiguration('value', 'http://' . config::byKey('internalAddr') . ':3456/' . $this->getConfiguration('request') . "&device=" . $eqLogic->getConfiguration('serial'));
        }
        $actionInfo = alexaapiCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getName());
        //if (is_object($actionInfo)) $this->setId($actionInfo->getId());
        if (($this->getType() == 'action') && ($this->getConfiguration('infoName') != '')) { //Si c'est une action et que Commande info est renseigné
            $actionInfo = alexaapiCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getConfiguration('infoName'));
            if (!is_object($actionInfo)) { //C'est une commande qui n'existe pas
                $actionInfo = new alexaapiCmd();
                $actionInfo->setType('info');
                $actionInfo->setSubType('string');
                $actionInfo->setConfiguration('taskid', $this->getID());
                $actionInfo->setConfiguration('taskname', $this->getName());
            }
            $actionInfo->setName($this->getConfiguration('infoName'));
            $actionInfo->setEqLogic_id($this->getEqLogic_id());
            //$actionInfo->save();
            $this->setConfiguration('infoId', $actionInfo->getId());
        }*/
     //   log::add('alexafiretv', 'debug', '**********************preSave2 '.$this->getName().'***********************************'.$this->getLogicalId());
    }

  public function lanceCommande($_name,$_cmd)
    {
		//	 		log::add('alexafiretv', 'debug', 'LANCE lanceCommande cmd');

	$reponse = '';
		$host = alexafiretv::safeAdbHost($this->getEqLogic()->getConfiguration('adresseip'));
		if ($host !== false && $this->getEqLogic()->testFireTVConnexion($this->getEqLogic()->getName(), $host)) {
	log::add('alexafiretv', 'info', " ╔══════════════════════[Lance Commande Action ".$_name."]════════════════════════════════════════════════════════════════════════════");
	$commande=self::prefixeRoot2() . "adb -s ".escapeshellarg($host.":5555")." shell ". $_cmd ;
	//$commande="sudo adb -s 192.168.0.38:5555 shell dumpsys window displays | grep init | cut -c45-53";
	//$commande="ls ";
//	$reponse=trim(shell_exec($commande));
	log::add('alexafiretv', 'debug', '╠═══> Envoi de '.$commande);
	$reponse=exec($commande, $output, $intout);
	//$reponse=shell_exec($commande);
//	log::add('alexafiretv', 'info', "*".$reponse."*");;
//	log::add('alexafiretv', 'info', "output*".json_encode($output, true)."*");
//	log::add('alexafiretv', 'info', "intout*".$intout."*");
	log::add('alexafiretv', 'info', " ╠═══> Réponse à la commande ".$_name.' = '.$intout.' (0=OK)');
		log::add('alexafiretv', 'info', " ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════");

/*$cmd = "ls /etc/passwd_non_existing > /dev/null 2>&1; echo $?";
$status = shell_exec("$cmd");
$status = rtrim($status);
log::add('alexafiretv', 'info', " failed command shell status=$status\n");*/
	}
	
	return $reponse;
  	}
	
  public function prefixeRoot2()
    {
	return (function_exists('posix_geteuid') && posix_geteuid() === 0) ? "" : "sudo ";
  	}
	
    public function execute($_options = null)
    {
		//log::add('alexafiretv', 'debug', "execute");
			 //		log::add('alexafiretv', 'debug', 'LANCE execute cmd');
		
        if ($this->getLogicalId() == 'refresh') {
            $this->getEqLogic()->refresh();
            return;
        }
        $request = $this->buildRequest($_options);
		/*
			$cmd = $this->getCmd(null, $LogicalId);
//	if ((!is_object($cmd)) || $forceUpdate) {
		if (!is_object($cmd)) {
			log::add('alexafiretv', 'debug', '╠═══> Commande absente '.$LogicalId);
		}
	$shellExec = $cmd->getConfiguration('request', $_shellExecDefault);
	$_resultat=$this->lanceCmd($LogicalId,$shellExec);

		
		*/
		
		
		
		
		/*
        log::add('alexafiretv', 'info', 'Envoi de ' . $request); //Request : http://192.168.0.21:3456/volume?value=50&device=G090LF118173117U
        $request_http = new com_http($request);
        $request_http->setAllowEmptyReponse(true); //Autorise les réponses vides
        if ($this->getConfiguration('noSslCheck') == 1) $request_http->setNoSslCheck(true);
        if ($this->getConfiguration('doNotReportHttpError') == 1) $request_http->setNoReportError(true);
        if (isset($_options['speedAndNoErrorReport']) && $_options['speedAndNoErrorReport'] == true) { // option non activée
            $request_http->setNoReportError(true);
            $request_http->exec(0.1, 1);
            return;
        }
        $result = $request_http->exec($this->getConfiguration('timeout', 3), $this->getConfiguration('maxHttpRetry', 3)); //Time out à 3s 3 essais
        if (!$result) throw new Exception(__('Serveur injoignable', __FILE__));
        // On traite la valeur de resultat (dans le cas de whennextalarm par exemple)
        $resultjson = json_decode($result, true);
        if (isset($resultjson['value'])) $value = $resultjson['value'];
        else $value = "";
        if (isset($resultjson['detail'])) $detail = $resultjson['detail'];
        else $detail = "";
        //log::add('alexafiretv', 'info', 'resultjson:'.json_encode($resultjson));
        // Ici, on va traiter une commande qui n'a pas été executée correctement (erreur type "Connexion Close")
        if (($value == "Connexion Close") || ($detail == "Unauthorized")) {
            //$value = $resultjson['value'];
            //$detail = $resultjson['detail'];
            log::add('alexafiretv', 'debug', '**On traite ' . $value . $detail . ' Connexion Close** dans la Class');
            sleep(6);
            if (ob_get_length()) {
                ob_end_flush();
                flush();
            }
            log::add('alexafiretv', 'debug', '**On relance ' . $request);
            $result = $request_http->exec($this->getConfiguration('timeout', 2), $this->getConfiguration('maxHttpRetry', 3));
            if (!result) throw new Exception(__('Serveur injoignable', __FILE__));
            $jsonResult = json_decode($json, true);
            if (!empty($jsonResult)) throw new Exception(__('Echec de l\'execution: ', __FILE__) . '(' . $jsonResult['title'] . ') ' . $jsonResult['detail']);
            $resultjson = json_decode($result, true);
            $value = $resultjson['value'];
        }

        if (($this->getType() == 'action') && (is_array($this->getConfiguration('infoNameArray')))) {
            foreach ($this->getConfiguration('infoNameArray') as $LogicalIdCmd) {
                $cmd = $this->getEqLogic()->getCmd(null, $LogicalIdCmd);
                if (is_object($cmd)) {
                    $this->getEqLogic()->checkAndUpdateCmd($LogicalIdCmd, $resultjson[0][$LogicalIdCmd]);
                    //log::add('alexafiretv', 'info', $LogicalIdCmd.' prévu dans infoNameArray de '.$this->getName().' trouvé ! '.$resultjson[0]['whennextmusicalalarminfo'].' OK !');
                } else {
                    log::add('alexafiretv', 'warning', $LogicalIdCmd . ' prévu dans infoNameArray de ' . $this->getName() . ' mais non trouvé ! donc ignoré');
                }
            }
        } elseif (($this->getType() == 'action') && ($this->getConfiguration('infoName') != '')) {
            // Boucle non testée ici mais fonctionne sur Alexa-SmartHome!!
            $LogicalIdCmd = $this->getConfiguration('infoName');
            $cmd = $this->getEqLogic()->getCmd(null, $LogicalIdCmd);
            if (is_object($cmd)) {
                $this->getEqLogic()->checkAndUpdateCmd($LogicalIdCmd, $resultjson[$LogicalIdCmd]);
            } else {
                log::add('alexafiretv', 'warning', $LogicalIdCmd . ' prévu dans infoName de ' . $this->getName() . ' mais non trouvé ! donc ignoré');
            }
        }*/
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

				
/*
		
        switch ($command) {
            case 'volume':
                $request = $this->build_ControledeSliderSelectMessage($_options, '50');
                break;
            case 'playlist':
            case 'routine':
                $request = $this->build_ControledeSliderSelectMessage($_options, "");
                break;
            case 'playmusictrack':
                $request = $this->build_ControledeSliderSelectMessage($_options, "53bfa26d-f24c-4b13-97a8-8c3debdf06f0");
                break;
            case 'speak':
            case 'announcement':
            case 'push':
            case 'multiplenext':
                $request = $this->build_ControledeSliderSelectMessage($_options);
                break;
            case 'reminder':
            case 'alarm':
                $now = date("Y-m-d H:i:s", strtotime('+3 second'));
                $request = $this->build_ControleWhenTextRecurring($now, "Ceci est un essai", $_options);
                break;
            case 'radio':
                $request = $this->build_ControledeSliderSelectMessage($_options, 's2960');
                break;
            case 'SmarthomeCommand':
                $request = $this->build_ControledeSliderSelectMessage();
                break;
            case 'command':
                $request = $this->build_ControledeSliderSelectMessage($_options, 'pause');
                break;
            case 'whennextalarm':
            case 'whennextmusicalalarm':
            case 'musicalalarmmusicentity':
            case 'whennextreminderlabel':
            case 'whennextreminder':
                $request = $this->build_ControlePosition($_options);
                break;
            case 'updateallalarms':
                $request = $this->build_ControleRien($_options);
                break;
            case 'deleteallalarms':
                $request = $this->buildDeleteAllAlarmsRequest($_options);
                break;
            case 'deleteReminder':
                $request = $this->buildDeleteReminderRequest($_options);
                break;
            case 'restart':
                $request = $this->buildRestartRequest($_options);
                break;
            default:
                $request = '';
                break;
        }
        //log::add('alexafiretv_debug', 'debug', '----RequestFinale:'.$request);
        $request = scenarioExpression::setTags($request);
        if (trim($request) == '') throw new Exception(__('Commande inconnue ou requête vide : ', __FILE__) . print_r($this, true));
        $device = str_replace("_player", "", $this->getEqLogic()->getConfiguration('serial'));
        return 'http://' . config::byKey('internalAddr') . ':3456/' . $request . '&device=' . $device;
		*/
		return true;
    }


    private function build_ControledeSliderSelectMessage($_options = array(), $default = "Ceci est un message de test")
    {
        $cmd = $this->getEqLogic()->getCmd(null, 'volumeinfo');
        if (is_object($cmd))
            $lastvolume = $cmd->execCmd();

        $request = escapeshellcmd($this->getConfiguration('request'));
        //log::add('alexafiretv_node', 'info', '---->Request2:'.$request);
        //log::add('alexafiretv_node', 'debug', '---->getName:'.$this->getEqLogic()->getCmd(null, 'volumeinfo')->execCmd());
        if ((isset($_options['slider'])) && ($_options['slider'] == "")) $_options['slider'] = $default;
        if ((isset($_options['select'])) && ($_options['select'] == "")) $_options['select'] = $default;
        if ((isset($_options['message'])) && ($_options['message'] == "")) $_options['message'] = $default;
        if (!(isset($_options['slider']))) $_options['slider'] = "";
        if (!(isset($_options['select']))) $_options['select'] = "";
        if (!(isset($_options['message']))) $_options['message'] = "";
        if (!(isset($_options['volume']))) $_options['volume'] = "";
        //log::add('alexafiretv_node', 'info', 'xxxxxxxxxxxxx---->_options:'.json_encode($_options));
        // Si on est sur une commande qui utilise volume, on va remettre après execution le volume courant
        if (strstr($request, '&volume=')) $request = $request . '&lastvolume=' . $lastvolume;
        $request = str_replace(
            array('#slider#', '#select#', '#message#', '#volume#'),
            array($_options['slider'], $_options['select'], urlencode(self::decodeTexteAleatoire($_options['message'])), $_options['volume']),
            $request
        );
        //log::add('alexafiretv_node', 'info', '---->RequestFinale:'.$request);
        return $request;
    }

    //private function trouveVolumeDevice() {
    //	$logical_id = $this->getEqLogic()->getCmd(null, 'volumeinfo')->getValue();
    //	$alexafiretv=alexafiretv::byLogicalId($logical_id, 'alexafiretv');getValue
    //}


    public static function decodeTexteAleatoire($_text)
    {
        $return = $_text;
        if (strpos($_text, '|') !== false && strpos($_text, '[') !== false && strpos($_text, ']') !== false) {
            $replies = interactDef::generateTextVariant($_text);
            $random = rand(0, count($replies) - 1);
            $return = $replies[$random];
        }
        preg_match_all('/{\((.*?)\) \?(.*?):(.*?)}/', $return, $matches, PREG_SET_ORDER, 0);
        $replace = array();
        if (is_array($matches) && count($matches) > 0) {
            foreach ($matches as $match) {
                if (count($match) != 4) {
                    continue;
                }
                $replace[$match[0]] = (jeedom::evaluateExpression($match[1])) ? trim($match[2]) : trim($match[3]);
            }
        }
        return str_replace(array_keys($replace), $replace, $return);
    }


    private function build_ControleWhenTextRecurring($defaultWhen, $defaultText, $_options = array())
    {
        $request = escapeshellcmd($this->getConfiguration('request'));
        //log::add('alexafiretv', 'debug', '----build_ControledeSliderSelectMessage RequestFinale:'.$request);
        //log::add('alexafiretv', 'debug', '----build_ControledeSliderSelectMessage _optionsAVANT:'.json_encode($_options));
        if ((!isset($_options['sound'])) && (!isset($_options['message'])) && (!isset($_options['when']))) {
            if (isset($_options['select'])) { // On est dans le cas d'un son d'alarme envoyé depuis le widget
                $_options['sound'] = urlencode($_options['select']);
                $_options['select'] = "";
            }
        }
        if ($_options['when'] == "") $_options['when'] = $defaultWhen;
        if ($_options['message'] == "") $_options['message'] = $defaultText;
        if ($_options['sound'] == "") $_options['sound'] = 'system_alerts_melodic_01';
        $request = str_replace(array('#when#', '#message#', '#recurring#', '#sound#'), array(urlencode($_options['when']), urlencode($_options['message']), urlencode($_options['select']), $_options['sound']), $request);
        return $request;
    }

    private function build_ControlePosition($_options = array())
    {
        $request = escapeshellcmd($this->getConfiguration('request'));
        $request = str_replace('#position#', urlencode($_options['position']), $request);
        return $request;
    }

    private function build_ControleRien($_options = array())
    {
        return escapeshellcmd($this->getConfiguration('request')) . "?truc=vide";
    }

    private function buildDeleteAllAlarmsRequest($_options = array())
    {
        $request = $this->getConfiguration('request');
        log::add('alexafiretv', 'debug', '----buildDeleteAllAlarmsRequest RequestFinale:' . $request);
        if ($_options['type'] == "") $_options['type'] = "alarm";
        if ($_options['status'] == "") $_options['status'] = "ON";
        return str_replace(array('#type#', '#status#'), array($_options['type'], $_options['status']), $request);
    }

    private function builddeleteReminderRequest($_options = array())
    {
        $request = $this->getConfiguration('request');
        if ($_options['id'] == "") $_options['id'] = "ManqueID";
        if ($_options['status'] == "") $_options['status'] = "ON";
        return str_replace(array('#id#', '#status#'), array($_options['id'], $_options['status']), $request);
    }

    private function buildRestartRequest($_options = array())
    {
        log::add('alexafiretv_debug', 'debug', '------buildRestartRequest---UTILISE QUAND ???--A simplifier--------------------------------------');
        $request = $this->getConfiguration('request') . "?truc=vide";
        return str_replace('#volume#', $_options['slider'], $request);
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
/*
	public static function getKnownDeviceType() {
		// récupéré de https://github.com/Apollon77/ioBroker.alexa2/blob/master/main.js
		$knownDeviceType = array(
			('A10A33FOX2NUBK') => array( (TypeEcho) => 'Echo Spot', (commandSupport) => 'true', (icon) => 'spot'),
			('A12GXV8XMS007S') => array( (TypeEcho) => 'FireTV', (commandSupport) => 'false', (icon) => 'firetv'), 
			('A15ERDAKK5HQQG') => array( (TypeEcho) => 'Sonos', (commandSupport) => 'false', (icon) => 'sonos'),
			('A17LGWINFBUTZZ') => array( (TypeEcho) => 'Anker Roav Viva Alexa', (commandSupport) => 'false', (icon) => 'other'),
			('A18O6U1UQFJ0XK') => array( (TypeEcho) => 'Echo Plus 2.Gen', (commandSupport) => 'true', (icon) => 'echo_plus2'), 
			('A1DL2DVDQVK3Q') => array( (TypeEcho) => 'Apps', (commandSupport) => 'false', (icon) => 'other'), 
			('A1H0CMF1XM0ZP4') => array( (TypeEcho) => 'Echo Dot/Bose', (commandSupport) => 'false', (icon) => 'other'), 
			('A1J16TEDOYCZTN') => array( (TypeEcho) => 'Fire tab', (commandSupport) => 'true', (icon) => 'firetab'),
			('A1NL4BVLQ4L3N3') => array( (TypeEcho) => 'Echo Show', (commandSupport) => 'true', (icon) => 'echo_show'), 
			('A1RTAM01W29CUP') => array( (TypeEcho) => 'Windows App', (commandSupport) => 'false', (icon) => 'other'), 
			('A1X7HJX9QL16M5') => array( (TypeEcho) => 'Bespoken.io', (commandSupport) => 'false', (icon) => 'other'),
			('A21Z3CGI8UIP0F') => array( (TypeEcho) => 'Apps', (commandSupport) => 'false', (icon) => 'other'), 
			('A2825NDLA7WDZV') => array( (TypeEcho) => 'Apps', (commandSupport) => 'false', (icon) => 'other'), 
			('A2E0SNTXJVT7WK') => array( (TypeEcho) => 'Fire TV V1', (commandSupport) => 'false', (icon) => 'firetv'),
			('A2GFL5ZMWNE0PX') => array( (TypeEcho) => 'Fire TV', (commandSupport) => 'true', (icon) => 'firetv'), 
			('A2IVLV5VM2W81') => array( (TypeEcho) => 'Apps', (commandSupport) => 'false', (icon) => 'other'), 
			('A2L8KG0CT86ADW') => array( (TypeEcho) => 'RaspPi', (commandSupport) => 'false', (icon) => 'other'), 
			('A2LWARUGJLBYEW') => array( (TypeEcho) => 'Fire TV Stick V2', (commandSupport) => 'false', (icon) => 'firetv'), 
			('A2M35JJZWCQOMZ') => array( (TypeEcho) => 'Echo Plus', (commandSupport) => 'true', (icon) => 'echo'), 
			('A2M4YX06LWP8WI') => array( (TypeEcho) => 'Fire Tab', (commandSupport) => 'false', (icon) => 'firetab'), 
			('A2OSP3UA4VC85F') => array( (TypeEcho) => 'Sonos', (commandSupport) => 'true', (icon) => 'sonos'), 
			('A2T0P32DY3F7VB') => array( (TypeEcho) => 'echosim.io', (commandSupport) => 'false', (icon) => 'other'),
			('A2TF17PFR55MTB') => array( (TypeEcho) => 'Apps', (commandSupport) => 'false', (icon) => 'other'), 
			('A32DOYMUN6DTXA') => array( (TypeEcho) => 'Echo Dot 3.Gen', (commandSupport) => 'true', (icon) => 'echo_dot3'),
			('A37SHHQ3NUL7B5') => array( (TypeEcho) => 'Bose Homespeaker', (commandSupport) => 'false', (icon) => 'other'), 
			('A38BPK7OW001EX') => array( (TypeEcho) => 'Raspberry Alexa', (commandSupport) => 'false', (icon) => 'raspi'), 
			('A38EHHIB10L47V') => array( (TypeEcho) => 'Echo Dot', (commandSupport) => 'true', (icon) => 'echo_dot'), 
			('A3C9PE6TNYLTCH') => array( (TypeEcho) => 'Multiroom', (commandSupport) => 'true', (icon) => 'multiroom'), 
			('A3H674413M2EKB') => array( (TypeEcho) => 'echosim.io', (commandSupport) => 'false', (icon) => 'other'),
			('A3HF4YRA2L7XGC') => array( (TypeEcho) => 'Fire TV Cube', (commandSupport) => 'true', (icon) => 'other'), 
			('A3NPD82ABCPIDP') => array( (TypeEcho) => 'Sonos Beam', (commandSupport) => 'true', (icon) => 'sonos'), 
			('A3R9S4ZZECZ6YL') => array( (TypeEcho) => 'Fire Tab HD 10', (commandSupport) => 'true', (icon) => 'firetab'), 
			('A3S5BH2HU6VAYF') => array( (TypeEcho) => 'Echo Dot 2.Gen', (commandSupport) => 'true', (icon) => 'echo_dot'), 
			('A3SSG6GR8UU7SN') => array( (TypeEcho) => 'Echo Sub', (commandSupport) => 'true', (icon) => 'echo_sub'), 
			('A7WXQPH584YP') => array( (TypeEcho) => 'Echo 2.Gen', (commandSupport) => 'true', (icon) => 'echo2'), 
			('AB72C64C86AW2') => array( (TypeEcho) => 'Echo', (commandSupport) => 'true', (icon) => 'echo'), 
			('ADVBD696BHNV5') => array( (TypeEcho) => 'Fire TV Stick V1', (commandSupport) => 'false', (icon) => 'firetv'), 
			('AILBSA2LNTOYL') => array( (TypeEcho) => 'reverb App', (commandSupport) => 'false', (icon) => 'reverb'),
			('AVE5HX13UR5NO') => array( (TypeEcho) => 'Logitech Zero Touch', (commandSupport) => 'false', (icon) => 'other'), 
			('AWZZ5CVHX2CD') => array( (TypeEcho) => 'Echo Show 2.Gen', (commandSupport) => 'true', (icon) => 'echo_show2')
		);
		return $knownDeviceType;
	}
*/
