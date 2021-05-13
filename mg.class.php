<?php

/************************************************************************************************************************
*												class mg pour Jeedom													*
*																														*
*	Déclaration dans les scénarios	:																					*
*					include_once getRootPath() . "pathRef/mg.class.php"; mg::init();									*
*																														*
* Partiellement inspiré par : http://rulistaff.free.fr/sc/sc_framework.zip												*
*																														*
************************************************************************************************************************/

use Sabre\VObject;
use ICal\ICal;

/*---------------------------------------------- *** fonctions de classe *** ------------------------------------------------*/
class mg {

	/*--------------------------------------- *** Constantes et variables de classe *** -------------------------------------*/

	// S'utilise comme une fonction, exemple : mg::ORANGE, self::$__noLog;
	// Constante de couleurs
	const	NOIR	= '#000000';
	const	ROUGE	= '#FF0000';
	const	VERT	= '#00FF00';
	const	BLEU	= '#0000FF';
	const	BLANC	= '#FFFFFF';
	const	JAUNE	= '#FFFF00';
	const	CYAN	= '#00FFFF';
	const	ORANGE	= '#CC5500';

	static $__log_INFO = 'INFO : ';				// Affichage des messages user
	static $__log_ERROR = '*** ERROR *** ';		// Affichage des messages d'erreurs
	static $__log_WARNING = '--- WARNING --- ';	// Affichage des messages d'alarme
	static $__log_INF2 = 'INF2 : ';				// Affichage des messages info des routines de type getParam ou setXxxx
	static $__log_SP = 'SP : ';					// Affichage des messages résultats des routines

	private static $__debug = 3;
	private static $__wait_cmd = true;
	private static $__stop_exception = 'Arrêt forcé du scénario';

	// Variables globales
	var $tabParams, $scenario, $debugVolet, $scenarioVoletManuel;

	/**************************************************************************************************************************
	*				INIT de la class, Calcul la variable $__debug en image du paramètrage 'Log' des scénarios, si param 'del' renseigné, pas d'effacement du log.
	**************************************************************************************************************************/
	function init($del='') {
		global $scenario, $tabParams, $scenarioVoletManuel;
		$tabParams = self::getVar('tabParams');
		$scenarioVoletManuel = 107;

		$mode = $scenario->getConfiguration('logmode'/*, 'default'*/);
		// Passe $debug selon le 'mode' du scénario.
		if ($mode == 'realtime') { $debug = 5; }
		elseif ($mode == 'default') { $debug = 4; }
		else { $debug = -1; }
		self::delLog($del);
		self::debug($debug, $mode);
	}

/************************************************************************************************************************
*																														*
*									 SOUS FONCTIONS PROPRE A LA DOMOTIQUE MG											*
*																														*
************************************************************************************************************************/
function FONCTIONS_DOMOTIQUE(){}

/************************************************************************************************************************
* DOMO													FULLY KIOSK														*
*************************************************************************************************************************
* Pilotage de la Gateway FullyKiosk																						*
* IMPORTANT : cf https://www.ozerov.de/fully-kiosk-browser/#configuration pour le détail de l'API						*
*	Paramètres :																										*
*		$cmd	:	Nom de la commande à activer																		*
*		$message : Valeur de base à envoyer avec la commande															*
*		$complement : Valeur complémentaire éventuelle à envoyer (par exemple le volume)								*
************************************************************************************************************************/
	function FullyKiosk($cmd, $message = '', $complement = '') {
		$cmd = strtoupper($cmd);
		$tabUser = mg::getVar('tabUser');
		$urlFully = 'HTTP://' . $tabUser['FullyKiosk']['IP'] . ':2323';
		$password = self::getParam('systeme', 'passwordFullyKiosk');
		$logTimeLine = self::getParam('Log', 'timeLine');
		$prefixe = '';

		// --------------------------------------------------- Screen On / Off ------------------------------------------
		if ($cmd == 'SCREEN') {
			$message = (strtoupper($message) == 'ON') ? 'On' : 'Off';
			$requete = "";
			if ($message == 'On') {
				$prefixe = 'cmd=toForeground'; // retour au premier plan
				$requete = "$urlFully/?$prefixe&$requete&password=$password";
				file_get_contents($requete);
			}
			$prefixe = "cmd=screen$message&"; // Screen On/Off
		}

		// ------------------------------------------------ Redémarrer l'application ------------------------------------
		elseif ($cmd == 'RESTART') {
			$requete = "cmd=restartApp";
		}

		// ------------------------------------------------ Chargement d'une URL ----------------------------------------
		elseif ($cmd == 'URL') {
			$message = urlencode($message);
			$prefixe = "cmd=loadURL&url=";
			$requete = "$message";
		}

		// ------------------------------------------------ Changement de design ----------------------------------------
		elseif ($cmd == 'DESIGN') {
			$IP_Jeedom = self::getTag('#IP#');
			$prefixe = "cmd=loadURL&url=";
			$requete = "http://$IP_Jeedom/index.php?v=d&p=plan&plan_id=$message";
		}

		// --------------------------------------------------------- TTS ------------------------------------------------
		elseif ($cmd == 'TTS') {
			// Réglage volume
			$volume = ($complement > 0 ? $complement : self::getParam('Media', 'JPI_TTS_Vol'));
			$prefixe = "cmd=setAudioVolume&level=$volume";
			$requete = "$complement&stream=3";
			$requete = "$urlFully/?$prefixe$requete&password=$password";
			file_get_contents($requete);
			self::message('', self::$__log_SP . __FUNCTION__ . " Requète : $requete");

			// Requète TTS
			$prefixe = "cmd=textToSpeech&text=";
			$requete = $message; sleep(2);
		}
		// ----------------------------------------- FONCTIONS NON IMPLEMENTEE ------------------------------------------
		else {
			self::message($logTimeLine, "**ERROR** la fonction '$cmd' n'est pas implémentée pour FullyKiosk");
			return;
		}

		// Lancement de la requète
		$requete = "$urlFully/?$prefixe$requete&password=$password";
		file_get_contents($requete);
		self::message('', $requete);
	}

/************************************************************************************************************************
* DOMO															SONOS													*
*************************************************************************************************************************
* Pilotage de du TTS sur SONOS																							*
*	Paramètres :																										*
*		Screen	:	0n/off																								*
*		Url		:	URL (format http://xxxxxxxxxxxxxxx)																	*
*		$cmd	:	Nom de la commande à activer																		*
*		$message : Valeur de base à envoyer avec la commande															*
*		$complement : Valeur complémentaire éventuelle à envoyer (par exemple le volume)								*
************************************************************************************************************************/
	function SONOS($cmd, $message = '', $complement = '') {
		$cmd = strtoupper($cmd);
		$equipSonos = self::getParam('Media', 'equipSonos');
		$infStatusSonos = self::mkCmd($equipSonos, 'Status');
		$infVolSonos = self::mkCmd($equipSonos, 'Volume Status');
		$sonosVolumeDefaut = self::getParam('Media', 'sonosVolumeDefaut');
		$jingleVol = ($complement > 0 ? $complement : self::getParam('Media', 'sonos_Jingle_Vol'));
		$jingleFile = self::getParam('Media', 'jingle_File');
		$logTimeLine = self::getParam('Log', 'timeLine');
		if ($message[0] == '@') { $jingle = 1; }
		$message = trim($message, '@');

	// ----------------------------------------------------------- PLAY ------------------------------------------------
		if ($cmd == 'PLAY') {
			self::setCmd($equipSonos, 'Volume', $jingleVol);
			self::wait ("$infVolSonos == $jingleVol", 4);
			self::setCmd($equipSonos, 'Jouer favoris', '', $message);
			self::wait ("'$infStatusSonos' != 'Pause' && '$infStatusSonos' != 'Arrêté'", 4);

	// ----------------------------------------------------------- TTS ------------------------------------------------
	} elseif ($cmd == 'TTS') {
	// ----------------------------------------------------- CHOIX DE LA VOIX ------------------------------------------
		$TTS_Voix = self::getParam('Media', 'TTS_Voix');
		if ($TTS_Voix == 'Aléatoire') {
			$liste = array('voxygen.tts.fabienne', 'voxygen.tts.agnes', 'voxygen.tts.camille', 'VoiceRSS');
			$TTS_Voix = $liste[random_int(0,count($liste)-1)];
			self::message('', "Voix : $TTS_Voix");
		}

		// =========================================== VOIX VoiceRSS (Online) ===========================================
		if ($TTS_Voix == 'VoiceRSS') {
			$nom_File_TTS = 'VoiceRSS.mp3';
				self::VoiceRSS($nom_File_TTS, $message, self::getParam('Media', 'sonosPathMedia'));

			// ========================================= VOIX JPI (OffLine) =============================================
		} else {
			self::JPI('TTS_JPI_FILE', $message);
			$nom_File_TTS = 'JPI_TTS.wav';
		}

			// ============================================== STOP DU SONOS COURANT =====================================
		self::setCmd($equipSonos, 'Stop');
		self::wait ("'$infStatusSonos' != 'Pause' && '$infStatusSonos' != 'Arrêté'", 4);
			// ============================================ ENVOI DU JINGLE =============================================
		if (isset($jingle)) {
			self::setCmd($equipSonos, 'Non muet');
			self::setCmd($equipSonos, 'Volume', $jingleVol);
			self::wait ("$infVolSonos == $jingleVol", 4);

			self::setCmd($equipSonos, 'Jouer favoris', '', $jingleFile);
		self::wait ("'$infStatusSonos' != 'Pause' && '$infStatusSonos' != 'Arrêté'", 4);
		}
		// ================================================ ENVOI DU TTS ===============================================
		$volume = ($complement > 0 ? $complement : self::getParam('Media', 'sonos_TTS_Vol'));

		self::setCmd($equipSonos, 'Non muet');
		self::setCmd($equipSonos, 'Volume', $volume);
		self::wait ("$infVolSonos == $volume", 4);
		self::setCmd($equipSonos, 'Jouer favoris', '', $nom_File_TTS);
	}
	self::wait ("'$infStatusSonos' == 'Lecture'", 60);
	sleep(5);
	self::wait ("'$infStatusSonos' != 'Lecture'", 600);
	self::setCmd($equipSonos, 'Volume', $sonosVolumeDefaut);
	sleep(3);
	self::wait ("$infVolSonos == $sonosVolumeDefaut", 4);
	self::setCmd($equipSonos, 'Entrée de ligne');
	}

/************************************************************************************************************************
* DOMO															JPI														*
*************************************************************************************************************************
* Pilotage de de la Gateway JPI																							*
*	Paramètres :																										*
*		$cmd	:	Nom de la commande à activer																		*
*		$message : Valeur de base à envoyer avec la commande															*
*		$complement : Valeur complémentaire éventuelle à envoyer (par exemple le volume)								*
************************************************************************************************************************/
	function JPI($cmd, $message = '', $complement = '') {
		$cmd = strtoupper($cmd);

		$tabUser = mg::getVar('tabUser');
		$IP_JPI = 'HTTP://' . $tabUser['JPI']['IP'] . ':8080';

		$equipSonos = self::getParam('Media', 'equipSonos');
		$infStatusSonos = self::mkCmd($equipSonos, 'Status');
		$pathMedia = 'http://' . self::getTag('#IP#') . self::getParam('Media', 'sonosPathMedia');
		$jingleFile = self::getParam('Media', 'jingle_File');
		$logTimeLine = self::getParam('Log', 'timeLine');
		if ($message && $message[0] == '@') { $jingle = 1; }
		$message = trim($message, '@');

		// Normalisation des volumes de la tablette
//		$requete = "$IP_JPI/?action=setVolume&volume=100&stream=media";
//		file_get_contents($requete);

		$volume = $complement ? $complement : self::getParam('Media', 'JPI_TTS_Vol');
		if ($cmd == 'TTS') {
				$TTS_Voix = self::getParam('Media', 'TTS_JPI_Voix');
				if ($TTS_Voix == 'aléatoire') {
					$liste = array('voxygen.tts.fabienne', 'voxygen.tts.agnes', 'voxygen.tts.camille');
					$TTS_Voix = $liste[random_int(0,count($liste)-1)];
				}
		}

		// ------------------------------------------------- AFFICHE DOORBIRD -----------------------------------------
		$requete = '';
		if ($cmd == 'DOORBIRD') {
			$IP_Doorbird = $tabUser['DoorBird']['IP'];
		$loginDoorbird = self::getParam('systeme', 'LoginDoorbird');
		$passwordDoorbird = self::getParam('systeme', 'passwordDoorbird');
			$message = "http://$loginDoorbird:$passwordDoorbird@$IP_Doorbird/bha-api/view.html";
			$requete = "$IP_JPI/?action=goToUrl&url=$message";

		// ----------------------------------------------------- LaunchAPP --------------------------------------------
		} elseif ($cmd == 'LAUNCH') {
			$requete = "$IP_JPI/?action=launchApp&packageName=$message";

		// ------------------------------------------------------ REBOOT ---------------------------------------------
		} elseif ($cmd == 'REBOOT') {
			$requete = "$IP_JPI/?action=reboot";
		// ------------------------------------------------------ RESTART ---------------------------------------------
		} elseif ($cmd == 'RESTART') {
			$requete = "$IP_JPI/?action=restart&sleepBetween=5";

			// ------------------------------------------------ Screen On / Off ---------------------------------------
		} elseif ($cmd == 'SCREEN') {
			$message = (strtoupper($message) == 'ON') ? 'On' : 'Off';
			$requete = "$IP_JPI/?action=screen$message";

		// ------------------------------------------------ Chargement d'une URL --------------------------------------
		} elseif ($cmd == 'URL') {
			$requete = "$IP_JPI/?action=goToUrl&url=$message";

		// ------------------------------------------------ Configure Layout ------------------------------------
		} elseif ($cmd == 'LAYOUT') {
			$requete = "$IP_JPI/?action=configureLayout&buttons=0&webTitleBar=0&webZoomEnabled=1&androidFullScreen=1";

		// -------------------------------------------------------- DESIGN --------------------------------------------
		} elseif ($cmd == 'DESIGN') {
			$requete = "$IP_JPI/?action=configureLayout&buttons=0&webTitleBar=0&webZoomEnabled=1&androidFullScreen=1";
			file_get_contents($requete);
			$requete = "$IP_JPI/?action=goToDesign&id=$message&fullscreen=1";
mg::message('', $requete);
		// ------------------------------------------------ Chargement d'une vue --------------------------------------

		} elseif ($cmd == 'VIEW') {
			$requete = "$IP_JPI/?action=goToView&id=$message";
		// ------------------------------------------------------ goToHome --------------------------------------------

		} elseif ($cmd == 'GOTOHOME') {
			$requete = "$IP_JPI/?action=goToHome";
		// ------------------------------------------------------ RESET WIFI ------------------------------------------

		} elseif ($cmd == 'RESETWIFI') {
			$requete = "$IP_JPI/action=resetWifi&sleepBetween=10";
		//----------------------------------------------------------- PLAY --------------------------------------------

		} elseif ($cmd == 'PLAY') {
			$requete = "$IP_JPI/?action=stop";

		//----------------------------------------------------------- STOP --------------------------------------------

		} elseif ($cmd == 'STOP') {
			$requete = "$IP_JPI/?action=play&media=$message&volume=$volume&queue=1&wait=0";
		//----------------------------------------------------------- SELECT_BT --------------------------------------------

		} elseif ($cmd == 'SELECT_BT') {
			$requete = "$IP_JPI/?action=manageBTDevice&deviceName=$message&BTaction=". ($complement==1 ? "connect" : "disconnect");
		//----------------------------------------------------------- VR_STATUS --------------------------------------------

		} elseif ($cmd == 'VR_STATUS') {
			$requete = "$IP_JPI/?action=VRstatus&status=$message";
		//----------------------------------------------------------- VOICE_CMD --------------------------------------------

		} elseif ($cmd == 'VOICE_CMD') {
			$requete = "$IP_JPI/?action=voiceCmd";

		//----------------------------------------------------------- VR_VEILLE --------------------------------------------

		} elseif ($cmd == 'VR_VEILLE') {
			$requete = "$IP_JPI/?action=voiceCmd&mode=.VEILLE&exit=1";

		//----------------------------------------------------------- SCENARIO --------------------------------------------

		} elseif ($cmd == 'SCENARIO') {
			$requete = "$IP_JPI/?action=$message".($complement ? "&#38;parametre=$complement" : '');
		// --------------------------------------------------------- SMS ----------------------------------------------

		} elseif ($cmd == 'SMS') {
			$requete = "$IP_JPI/?action=sendSms&message=" . utf8_decode($message) . " &number=$complement";
			$requete = str_replace(" ", "%20", $requete);
			self::message('', self::$__log_SP . __FUNCTION__ . "Envoi SMS '$message' à '$complement'");
		// ----------------------------------------------------- APPEL_JPI --------------------------------------------
		} elseif ($cmd == 'APPEL_JPI') {
			$requete = "$IP_JPI/?action=makeCall&message=$message&number=$complement";
			$requete = str_replace(" ", "%20", $requete);
			self::message('', self::$__log_SP . __FUNCTION__ . "Envoi SMS '$message' à '$complement'");
		}

		// Envoie de la requète
		if ($requete != '') {
			$file = @file_get_contents($requete);
			self::message('', "$requete => '$file'");
	// ------------------------------------------------------------------------------------------------------------------
	// ------------------------------------------------------------- TTS ------------------------------------------------
	// ------------------------------------------------------------------------------------------------------------------
			if (isset($jingle)) {
				$jingle_Vol = ($complement > 0 ? $complement : self::getParam('Media', 'JPI_Jingle_Vol'));
				$requete = "$IP_JPI/?action=play&media=$pathMedia/$jingleFile&volume=$jingle_Vol&queue=1&wait=0";
				file_get_contents($requete);
			}
			sleep(1);
		// ============================================ Voice Voxygen JPI ===============================================

		} elseif ($cmd == 'TTS' && $TTS_Voix != 'VoiceRSS') {
				$message = urlencode($message);
				$requete = "$IP_JPI/?action=tts&message=$message&volume=$volume&queue=1&wait=0";
				file_get_contents($requete);
				self::message('', $requete);
			// --------------------------------------------- TTS_JPI avec VoiceRSS --------------------------------------
		} elseif ($cmd == 'TTS' && $TTS_Voix == 'VoiceRSS') {
			// ================================================= VoiceRSS ===============================================
			$nom_File_TTS = 'VoiceRSS.mp3';
			self::VoiceRSS($nom_File_TTS, $message, self::getParam('Media', 'sonosPathMedia'));

			sleep(1);
			$requete = "$IP_JPI/?action=play&media=$pathMedia/$nom_File_TTS&volume=$volume&queue=1&wait=0";
			file_get_contents($requete);
			mg::message('', $requete);
		}
	}

/************************************************************************************************************************
* DOMO															GoogleCast												*
*************************************************************************************************************************
* Pilotage de de la Gateway GoogleCast																					*
*	Paramètres :																										*
*		$cmd	:	Nom de la commande à activer																		*
*		$message : Valeur de base à envoyer avec la commande															*
*		$complement : Valeur complémentaire éventuelle à envoyer (par exemple le volume)								*
*	cf https://github.com/guirem/plugin-googlecast/blob/develop/docs/fr_FR/index.md#utilisation-dans-un-sc%C3%A9nario	*
************************************************************************************************************************/
	function GoogleCast($cmd, $message = '', $complement = '') {
		$equipGoogleCast = self::getParam('Media', 'equipGoogleCast');
		$uuid = self::getParam('Media', 'uuidGoogleCast');
		$cmd = strtoupper($cmd);
		$volOrg = self::getCmd($equipGoogleCast, 'Volume');
		if ($message && $message[0] == '@') { $jingle = 1; }
		$message = trim($message, '@');

		if ($cmd == 'TTS') {
		// --------------------------------------------------------- TTS ------------------------------------------------
			if (isset($jingle)) {
				$jingleFile = self::getParam('Media', 'jingle_File');
				$volume = self::getParam('Media', 'googleCast_Jingle_Vol');
				$command_string = "cmd=notif|value=$jingleFile";
			self::_GoogleCast($equipGoogleCast, $uuid, $command_string, $volume);
			}
			// =========================================== VOIX VoiceRSS (Online) ===========================================
			$volume = $complement > 0 ? $complement : self::getParam('Media', 'googleCast_TTS_Vol');
			if (self::getParam('Media', 'googleCast_TTS_Voix') == 'VoiceRSS') {
				$nom_File_TTS = 'GoogleCast.mp3';
					self::VoiceRSS($nom_File_TTS, $message, self::getParam('Media', 'googleCastPathMedia'));
					sleep(2);
					$command_string = "cmd=notif|value=$nom_File_TTS";
				} else {
					// Voix standard
					$command_string = "cmd=tts|value=$message";
				}
				self::_GoogleCast($equipGoogleCast, $uuid, $command_string, $volume);
		}

		// --------------------------------------------------------- PLAY ------------------------------------------------
		else if ($cmd == 'PLAY') {
			$command_string = "cmd=notif|value=$message";
			$volume = ($complement > 0 ? $complement : self::getParam('Media', 'googleCast_Jingle_Vol'));
			self::_GoogleCast($equipGoogleCast, $uuid, $command_string, $volume);
		}
	}

	/************************************************************************************************************************
	/***********************************************************************************************************************/
	function _GoogleCast($equipGoogleCast, $uuid, $command_string, $volume) {
		$googlecast = googlecast::byLogicalId($uuid, 'googlecast');
		if ( !is_object($googlecast) || $googlecast->getIsEnable()==false ) {
		} else {
			if ($volume != self::getCmd($equipGoogleCast, 'volume')) { self::setCmd($equipGoogleCast, 'Volume niveau', $volume); }
			$ret = googlecast::helperSendNotifandWait_static($uuid, $command_string, 300, 500);
		}
	}

/************************************************************************************************************************
* DOMO														VOICE_RSS													*
*************************************************************************************************************************
*	Génération TTS via VoiceRSS : http://www.voicerss.org/api/															*
*	Paramètres :																										*
*		nom_File_TTS : Nom du fichier à générer dans le rep 'SonosPathMedia' de Jeedom									*
*		message : Message à transposer																					*
************************************************************************************************************************/
	function VoiceRSS($nom_File_TTS, $message, $pathMedia) {
			// Nettoyage du message
			$message = htmlspecialchars ($message);
			$pathRef = self::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
			include_once getRootPath() . ("$pathRef/ressources/voicerss_tts.php");
			$tts = new VoiceRSS;
			$params = [
				'key' => self::getParam('Media', 'API_VoiceRSS'),
				'hl' => 'fr-fr',
				'src' => $message,
				'r' => '0',
				'c' => 'mp3',
				'f' => '44khz_16bit_stereo',
				'ssml' => 'false',
				'b64' => 'false'
				];
			$voice = $tts->speech($params);
			$fileTTS = getRootPath() . "$pathMedia/$nom_File_TTS";
			sleep(1);
			file_put_contents($fileTTS, $voice['response']);
	}

/************************************************************************************************************************
* DOMO												VOLETS GENERAL														*
*************************************************************************************************************************
* Ouverture ou fermeture des volets par Zone.																			*
* La variable _VoletGeneral est positionnée avec le $sens à la fin.														*
* Cela permet de connaître le sens du dernier mouvement et pour l'appelant d'attendre la fin du process.				*
*																														*
* NB : Pour 'forcer un mouvement, commencer par faire un unsetVar('_VoletGeneral') ou paramètre $force = 1				*
*	Paramètres :																										*
*		_VoletsGroupe = (Zones à traiter séparées par des virgules)														*
*		VoletsSens = Monter, Descendre ou 'M', 'D'																		*
*		force = Force le mouvement																						*
*																														*
*	Exemple VoletsGeneral ('Etage, Salon, Chambre, SdBRdC'), 'M');														*
************************************************************************************************************************/
	function voletsGeneral ($voletsGroupe, $sensDemandé, $force = 0) {
		global $scenario, $debugVolet, $scenarioVoletManuel;
		$Log = "Log:_Volets/_TimeLine";
		$sensDemandé = strtoupper($sensDemandé[0]);

		// Abandon si déja en position
		if (!$force && $sensDemandé == self::getVar('_VoletGeneral')) {
		self::Message('', self::$__log_SP . __FUNCTION__ . " - Volets déja en position, Annulation de la demande ...");
			return;
		}

		// ---------------------------------------- Calcul du sens (nom et valeur) ------------------------------------
		if ($sensDemandé == 'M') {
			$sens = 'Monter'; $slider = 99;
			self::Message($Log, "Volets - Ouverture générale des volets - groupe : $voletsGroupe.");
		}
		else {
			$sens = 'Descendre'; $slider = 0.1;
			self::Message($Log, "Volets - Fermeture générale des volets - groupe : $voletsGroupe.");
		}

		// Lecture des Zones à traiter
		$tabZones = explode(',', $voletsGroupe);
		for ($i = 0; $i < count($tabZones); $i++) {
			$zone = trim($tabZones[$i]);
			$equipement = "[$zone][Volet_Général]";

			self::Message('', "----------------------------------$sens volet général du groupe : $zone -----------------------");
			// Volets général Lancé 2 fois, par sécurité
			for ($nb = 1; $nb <= 2; $nb++) {
				self::setCmd($equipement, $sens);
				sleep(1);
			}
		} // Fin for $TabZoneà traiter

		// Parcours de la table des volets pour fermeture individuelle
		$tabVolets = self::getVar('tabVolets');
		foreach ($tabVolets as $cmd => $details_Volet) {
			$zone = trim($details_Volet['zone']);
			$duree =  intval(trim($details_Volet['duree']));
			$groupe =  trim($details_Volet['groupe']);

			if (!$groupe || !$duree) { continue; }
			$voletInverse =	 trim($details_Volet['inverse']);

			// Uniquement si volet existe et volets dans le groupe
			if (strpos($voletsGroupe, $groupe) !== false) {
				// Si volet inversé
				if ($voletInverse == '-') {
					// Si descente on inverse (store banne)
					if( $slider == 0.1) { $slider = 99; }
					// Sinon on passe au suivant
				}
				mg::debug();
				self::Message('', "-------------- $sens ($sensDemandé) individuelle du volet : $cmd / $zone ($slider) --------------");
				mg::debug(-1);

				self::VoletRoulant($zone, $cmd, 'Slider', $slider);
				self::wait("scenario($scenarioVoletManuel) == 0", 180);
			}
		} // fin for each TabVolets
	self::setVar('_VoletGeneral', $sensDemandé);
}

/************************************************************************************************************************
* DOMO													VOLET ROULANT													*
*************************************************************************************************************************
* Gestion des volets roulant																							*
*	Gère un visuel proportionnel au temps de mouvement du volet ainsi que la possibilité d'ouvrir/fermer à x %			*
*	Si Slider <= 0.1 ou slider >= 99 on force le mouvement.																*
*	Une image de porte fenêtre est utilisé si un équipement d'ouverture/fermeture est déclaré.							*
*	Réglage largeur/hauteur via les variables du widget widhImage et heightImage										*
*																														*
*	Mettre la durée nécessaire à une ouverture ou une fermeture COMPLETE du volet dans @TabVolets/Duree_Mouvement		*
*	Le nom de l'équipement de commande de volet doit être celui du widget préfixé de 'Volet '							*
*																			(ex : Volet Bureaupour le widget Bureau).	*
*	Le nom de l'équipement de commande d'ouverture fermeture de fenêtre doit être celui du widget suffixé de '_O_'		*
*																				(ex : Bureau_O_pour le widget Bureau).	*
*	les noms de commande/info Etat, Slider, Slider_, Volet et Volet_ ne doivent pas être modifié dans le virtuel		*
************************************************************************************************************************/
	function voletRoulant($zone, $cmd, $trigger, $slider = '') {
		global $scenarioVoletManuel, $debugVolet;
		// mg::message('', "zone : '$zone' - cmd : '$cmd' - trigger '$trigger' - slider : '$slider'");

		self::setScenario($scenarioVoletManuel, 'Deactivate');

		$etat = 0;
		$equipement = "[$zone][Ouvertures]";

		if ($slider <= 0.1) { $slider = 0.1; }
		if ($slider >= 99) { $slider = 99; }

		$nbLamelles = 17; // Nb de lamelles dans le Widget moins une
		$increment = round(99 / $nbLamelles);

		if ($debugVolet) { self::message('', self::$__log_SP . __FUNCTION__ . " ************** MODE DEBUGVOLET - Volets inhibés !!! *******************"); }

		// ------------------------------------ RECUPERATION DES PARAMETRES DU VOLET ----------------------------------
		$duree_Mouvement = 0;
		$alerteOuvert = '';
		if ($cmd) {
			$tabVolets = (array)self::getVar('tabVolets');
			$details_Volet = $tabVolets[$cmd];
			$duree_Mouvement = intval($details_Volet['duree']);
			$alerteOuvert = trim($details_Volet['alerteOuvert']);
			$ouvrant = trim($details_Volet['ouvrant']);

		if($duree_Mouvement == 0) { $slider = 0.1 ; }
		}

		// Nom des équipements Volet et Ouverture
		if ($duree_Mouvement > 0) {
			$equipVolet = "[$zone][$cmd]";
		} else {
			$equipVolet = '';
		}
		if ($alerteOuvert != '!') {
			$equipOuverture = "[$zone][$ouvrant".']';
			$cdOuvert = self::getCmd($equipOuverture, 'Ouverture');
		}
		else {
			$cdOuvert = 0;
		}

		// ------------------------------- CALCUL DU TYPE ---------------------------------
		if ($cdOuvert) { $type = 100; } //Image fenêtre ouverte
		else { $type = 0; } // Image fenêtre fermée

		// Calcul de l'état du volet corrigé du type pour le widget
		if(self::existCmd($equipement, "$cmd"." Aff")) {
			$volet_Aff = self::getCmd($equipement, "$cmd"." Aff");
		} else { $volet_Aff = 0.1; }
		if ($volet_Aff > 99) { $volet_Aff = round($volet_Aff - 100); }
		if ($volet_Aff <= 1) { $volet_Aff = 0.1; }

		// -------------------------------------- INIT DU VOLET (pour éviter les décalages) -----------------------------
		self::message('', self::$__log_SP . __FUNCTION__ . " *** INITIALISATION - Trigger : '$trigger' - Equipement : $equipement - Type : $type - EquipVolet : $equipVolet - ( $volet_Aff ==> $slider)");

		// On sort si simple MàJ pour ouverture/fermeture fenêtre
		if ($trigger == 'Ouverture' || $duree_Mouvement <= 0 || !self::existCmd($equipement, "$cmd"." Aff")) {
			self::VoletRoulantArret($equipement, $equipVolet, $cmd, $volet_Aff, $slider, $type, '*** FIN SUR Mise à jour Ouvert / Fermé de ' . $equipement);
			sleep(1);
			return;
		}

		// ------------------------ CALCUL DE L'ETAT -----------------------------
		if ($slider > $volet_Aff && $volet_Aff < 99) {
			$etat = 1;
			if ($debugVolet == false) { self::setCmd($equipVolet, 'Monter'); }
			self::message('', self::$__log_INF2 . __FUNCTION__ . " *** Montée via slider - Etat = $etat");
		}
		if ($slider < $volet_Aff && $volet_Aff > 0.1) {
			$etat = -1;
			if ($debugVolet == false) { self::setCmd($equipVolet, 'Descendre'); }
			self::message('', self::$__log_INF2 . __FUNCTION__ . " *** Descente via slider - Etat = $etat");
		}


		//*************************************************************************************************************
		$ratioFinDescente = 0.11;
		$ratioDebMontee = 0.23;
		$ratioVitesseMontee = 1.30;

		if ($etat == 0) {
			$dureeMouvementPonderee = 0;

		// Montée
		} elseif ($etat == 1) {
			$dureeMouvementPonderee = $duree_Mouvement	* $ratioVitesseMontee * (1 - $ratioDebMontee);

		// Descente
		} elseif ($etat == -1) {
			$dureeMouvementPonderee = $duree_Mouvement * (1 - $ratioFinDescente);
		}

		$dureePause = $dureeMouvementPonderee / $nbLamelles;
		//mg::message('Log:/_DEBUG', "************ $duree_Mouvement / $dureeMouvementPonderee - $dureePause *******************");

		//*************************************************************************************************************
		self::message('', self::$__log_SP . __FUNCTION__ . " *** BOUCLE $volet_Aff = > $slider - Etat : $etat - Type : $type - Incrément : $increment - Duree du Mouvement : $duree_Mouvement - DureeMouvementPonderee : $dureeMouvementPonderee - Durée de pause : " . round($dureePause, 2));

		// Délais de démarrage du volet avant la montée effective
		if ($volet_Aff <= 0.1 && $slider > 0.1) { usleep($dureeMouvementPonderee * $ratioDebMontee *1000000); }

		// boucle de mouvement visuel
		for ($i = 0; $i < $nbLamelles; $i++) {

		//*************************************************************************************************************
			 // Si volet montant
			 if ($etat == 1) {
				// Incrémentation du volet avec addition du type pour le widget
				$volet_Aff = round($volet_Aff + $increment);
				if ($volet_Aff > 99) {$volet_Aff = 99; };
				self::setInf($equipement, "$cmd"." Aff", $volet_Aff + $type);
				self::setCmd($equipement, $cmd . ' Slider', $volet_Aff);
				self::setCmd($equipement, 'Rafraichir');
				// ****************************************************************************************************

				// Si déclencheur == Slider et slider <= volet on stoppe physiquement et on sort
				if ( $trigger == 'Slider' && $slider < ($volet_Aff + $increment /2) && $slider < 99 ) {
					if ($debugVolet == false) { self::setCmd($equipVolet, 'Stop'); }
					self::VoletRoulantArret($equipement, $equipVolet, $cmd, $volet_Aff, $slider, $type, '$slider == $volet_Aff ==> sortie de la montée.');
					return;

				// si volet en haut on stoppe (on laisse le volet physique s'arréter seul)
				} else if ($volet_Aff >= 99) {
					self::VoletRoulantArret($equipement, $equipVolet, $cmd, $volet_Aff, $slider, $type, 'Montant - $volet_Aff >= 99');
					return;
				}
			}

		//*************************************************************************************************************
		 // sinon volet descendant
		 else if ($etat == -1) {
				// Décrémentation du volet avec addition du type pour le widget
				$volet_Aff = round($volet_Aff - $increment);
				if ($volet_Aff < 0.1) {$volet_Aff = 0.1; };
				self::setInf($equipement, "$cmd"." Aff", $volet_Aff + $type);
				self::setCmd($equipement, $cmd . ' Slider', $volet_Aff);
				self::setCmd($equipement, 'Rafraichir');
				// ****************************************************************************************************

				// Si déclencheur == Slider et slider >= volet on stoppe physiquement et on sort
				if ( $trigger == 'Slider' && $slider > ($volet_Aff - $increment /2) && $slider > 0.1) {
					if ($debugVolet == false) { self::setCmd($equipVolet, 'Stop'); }
					self::VoletRoulantArret($equipement, $equipVolet, $cmd, $volet_Aff, $slider, $type, '$slider == $volet_Aff ==> sortie de la descente.');
					return;
				}
				// si volet en bas (à zéro) on stoppe (on laisse le volet physique s'arréter seul)
				else if ($volet_Aff <= 0.1) {
					self::VoletRoulantArret($equipement, $equipVolet, $cmd, $volet_Aff, $slider, $type, 'Descendant - $volet_Aff < 1');
					return;
				}
			}
		 else if ($etat == 0) {
					self::VoletRoulantArret($equipement, $equipVolet, $cmd, $volet_Aff, $slider, $type, 'Rien à faire');
					return;
		 }
			// Temporisation
			if ($dureePause > 0) { usleep($dureePause * 1000000); }
		} // Fin de boucle de mouvement
	}

/************************************************************************************************************************
* DOMO												VoletRoulantArret													*
	// Arrêt et sortie du scénario
************************************************************************************************************************/
	function voletRoulantArret($equipement, $equipVolet, $cmd, $volet_Aff, $slider, $type, $message) {
	global $scenarioVoletManuel;

	if ($volet_Aff < 0.1) {$volet_Aff = 0.1; };
	if ($volet_Aff > 99) {$volet_Aff = 99; };
		if ($equipVolet) {
			self::setCmd($equipement, $cmd . ' Slider', $volet_Aff);
			self::setInf($equipement, "$cmd"." Aff", $volet_Aff + $type);
		}
	self::setScenario($scenarioVoletManuel, 'activate');
}

/************************************************************************************************************************
* DOMO													LAMPE COULEUR													*
*************************************************************************************************************************
* Gestion du signal lumineux du salon																					*
*	La variable _Lampe_Couleur contient les paramètres suivant séparés par une virgule (cf "Pour Test") :				*
*	 - Intensité																										*
*	 - Couleur (optionnel)																								*
*	 - N° du scénario éteignant la lampe (optionnel)																	*
*	 - Timing en seconde (optionnel). Attention extinction uniquement à la mn entière !									*
*	Au bout de timing la lampe est éteinte.																				*
*	Si intensité <= 1 => off (les autres paramètres sont optionnels).													*
************************************************************************************************************************/
	function lampeCouleur($equipement, $intensité=0, $couleur = '', $scenarioStopID = 90, $timing = 0) {
		if ($scenarioStopID == 0) { $scenarioStopID = 90; }
		if ($intensité <= 1) { $intensité = 0; }

		self::message('', self::$__log_SP . __FUNCTION__ . " : $equipement - $intensité - $couleur - $timing");

		// Demande d'extinction
		if (self::getCmd($equipement, 'Etat') > 0 && $intensité == 0) {
			self::setCmd($equipement, 'Off');
			return;
		}

		// Réglage de la couleur
		if ($couleur != '') { self::setCmd($equipement, 'Couleur', $couleur); }

		// Réglage de l'intensité
		if ($intensité > 0 ) {
			self::setCmd($equipement, 'Slider Intensité', $intensité);
		}

		//Gestion du timing
		if ($timing != '0' && $timing != 0) {
			self::setCron($scenarioStopID, self::gettag('#timestamp#') + $timing);
		}
	}

/************************************************************************************************************************
*																														*
*												SOUS FONCTIONS UTILITAIRES												*
*																														*
************************************************************************************************************************/
function FONCTIONS_UTILITAIRES(){}

/************************************************************************************************************************
* DOMO														ALERTE														*
*************************************************************************************************************************
* Système permettant de gérer la périodicité et le TimeOut des alertes.													*
*	Paramètres :																										*
*		$nom : Nom de l'alerte.																							*
*		$periodicite : Periodicité en mn. Si < 0, annulation de l'alerte.												*
*		$dureeTotale : Nombre de mn avant annulation de l'alerte.														*
*		$destinataires : Liste des destinataires du message d'alerte.													*
*		$message : Message à envoyer.																					*
*																														*
* Un cron est automatiquement posé pour le rappel																		*
* Une variable _alerte$nom est positionnée à time() au lancement de l'alerte											*
*																														*
* Usage :																												*
*			Lancement de l'appel : mg::alerte($nom, $periodicite, $dureeTotale, $destinataires, $message);				*
*			Rappel de l'alerte mg::alerte($nom);																		*
*			Annulation de l'alerte mg::alerte($nom , 0);																*
************************************************************************************************************************/
	function alerte($nom, $periodicite = 0, $dureeTotale = 60, $destinataires = '', $message = '') {
		if ($nom == '' ) { return; }
		$alertes = self::getVar('tabAlertes');

		// Si heure de fin dépassée ou périodicité == 0, Annulation de l'alerte
		if (($periodicite < 0 || (@$alertes[$nom]['fin'] && time() >= @$alertes[$nom]['fin']))) {
			// --------------------------------------------------------------------------------------------------------
			self::messageT('', "! Fin de l'alerte : $nom");
			// --------------------------------------------------------------------------------------------------------
			mg::unsetVar("_alerte$nom");
			unset($alertes[$nom]);
			self::unsetVar('tabAlertes', $alertes);
			self::setCron('', time()-60);
			return;
		}

		elseif ($periodicite > 0  && @$alertes[$nom]['nom'] == '') {
			// --------------------------------------------------------------------------------------------------------
			self::messageT('', "! Lancement de l'alerte : $nom - periodicite : $periodicite mn - fin dans " . $dureeTotale . " mn.");
			// --------------------------------------------------------------------------------------------------------
			$alertes[$nom]['nom'] = $nom;
			$alertes[$nom]['message'] = ($message ? $message : "alerte $nom");
			$alertes[$nom]['periodicite'] = $periodicite;
			$alertes[$nom]['debut'] = time();
			$alertes[$nom]['fin'] = time() + $dureeTotale*60;
			$alertes[$nom]['last'] = time();
			self::Message($destinataires, $alertes[$nom]['message']);
			self::setVar('tabAlertes', $alertes);
			mg::setVar("_alerte$nom", time());
			self::setCron('', time() + $periodicite*60);
			return;
		}

		elseif (time() - $alertes[$nom]['periodicite']*60 >= $alertes[$nom]['last'] && $periodicite > 0) {
			// --------------------------------------------------------------------------------------------------------
			self::messageT('', "! Rappel alerte : $nom - periodicite : " . $alertes[$nom]['periodicite'] . " mn - last rappel à " . date('H\h\ i\m\n', $alertes[$nom]['last']) . " - Debut à " . date('H\h\ i\m\n', $alertes[$nom]['debut']) . " - fin à " . date('H:i', $alertes[$nom]['fin']));
			// --------------------------------------------------------------------------------------------------------
			$duree = round((time() - $alertes[$nom]['debut'])/60);
			self::message($destinataires, $alertes[$nom]['message']. " depuis $duree minutes.");
			$alertes[$nom]['last'] = time();
			self::setVar('tabAlertes', $alertes);
			self::setCron('', time() + $alertes[$nom]['periodicite']*60);
		}

	mg::Message('', print_r($alertes, true));
}

/************************************************************************************************************************
* UTIL													GET PARAM														*
*************************************************************************************************************************
* Renvoi un paramètrage du tableau 'tabParams', crée le praramétre si il n'existe pas et $defaut présent				*
*	NB : Dans le cas d'une création automatique, la remarque sera préfixée de '*** '									*
*	Paramètres :																										*
*		Section : Nom de la section																						*
*		name : Nom de la valeur demandée																				*
*		Defaut : Valeur par defaut du paramètre																			*
*		remDefaut : Remarque par defaut du paramètre																	*
************************************************************************************************************************/
	function getParam($section, $name, $valDefaut=null, $remDefaut=null) {
		global $tabParams;
		$tmp = '';
		$section = strtolower($section);

		if (!$section) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le nom de la section ne peut pas être vide");
			return;
		}
		elseif (!$name) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le nom du paramètre ne peut pas être vide");
			return;
		}
		elseif (!isset($tabParams[$section][$name]) && $valDefaut == null) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le paramètre ($section - $name) n'existe pas et aucune valeur par defaut !!!");
		} else {
			$value = $tabParams[$section][$name]['value'];
			// Paramètre absent et value defaut OK, on crée le paramétre
			if ($value == null && $valDefaut != null) {
				$value = $valDefaut;
				$tabParams[$section][$name]['value'] = $value;
				$tabParams[$section][$name]['remarque'] = "*** $remDefaut";
				self::setVar('tabParams', $tabParams);
				$tmp = '*** par defaut *** Paramétre ajouté à la BdD !!!';
			}
			self::message('', self::$__log_INF2 . __FUNCTION__ . " : Paramètre : ('tabParams' / $section / $name) == '$value' $tmp");
			return trim($value);
		}
	}

/************************************************************************************************************************
* DOMO														WakeOnLane													*
*************************************************************************************************************************
*	Réveil WoL d'une station																							*
*	Paramètres :																										*
*		$nomStation = Nom de la station sur le réseau pour retrouver son adresse										*
*	Necessite de charger le paquet "apt-get install "wakeonlan															*
*	https://manpages.debian.org/buster/wakeonlan/wakeonlan.1.en.html													*
************************************************************************************************************************/
	function WakeOnLan($nomStation) {
		if ($nomStation != '') {
			$tabUser = mg::getVar('tabUser');
			$IP = $tabUser[$nomStation]['IP'];
			$MAC = $tabUser[$nomStation]['MAC'];

			if ($IP != '' && $MAC != '') {

				$regex = "(\d+[\.]\d+[\.]\d+[\.])";
				preg_match("/$regex/ui", $IP, $found);
				$IP = $found[0].'255';
				$wol = "wakeonlan -i $IP -p 7 $MAC";
				self::message('', self::$__log_INF2 . __FUNCTION__ . " : Wol : $wol");
				shell_exec($wol);
			}
		}
	}

/************************************************************************************************************************
* DOMO														EVENTGHOST													*
*************************************************************************************************************************
*	Pilotage de EventGhost																								*
* IMPORTANT : necessite l'installation et le paramètrage préalable de EVentGhost ( http://www.eventghost.net/ )			*
* sur la station à piloter																								*
*	Paramètres :																										*
*		$cmd = Valeur à envoyer à EventGhost sans le 'HTTP.'															*
*		$nomStation = Nom de la station sur le réseau pour retrouver son adresse										*
************************************************************************************************************************/
	function eventGhost($cmd, $nomStation='PC-MG') {
		$portEventGhost = '8082';
		if ($nomStation != '') {
			$tabUser = mg::getVar('tabUser');
			$IP_Station = $tabUser[$nomStation]['IP'];
			if ($IP_Station) {
				$requete = "http://$IP_Station". ":$portEventGhost/?HTTP.$cmd";
				self::message('', self::$__log_INF2 . __FUNCTION__ . " : Cmd : $cmd  - $requete");
				@file_get_contents("$requete");
			}
		}
	}

/************************************************************************************************************************
* UTIL													MINUTERIE														*
*************************************************************************************************************************
* On si nbMvmt ou cdAllumage																							*
* Off si sheduler et lastMvmt > Timer ou cdExtinction.																	*
* Positionne un cron à $Timer si lampe allumé.																			*
* Paramétres :																											*
*		$equipEcl : Nom de l'équipement à piloter																		*
*		$infNbMvmt : Nom de la commande donnant le Nb de mouvement dans la zone											*
*		$timer : Durée de la minuterie (2 par defaut)																	*
*		$cdExtinction : Condition d'extinction, true pour éteindre, false sinon											*
*		$cdAllumage :  Condition d'allumage, true pour allumer, false sinon												*
*		$actionEtat : Nom de la commande donnat l'état de l'équipement ('Etat' par defaut)								*
*																														*
* Exemple d'utilisation dans un bloc code :																				*
*-----------------------------------------------------------------------------------------------------------------------*
include_once getRootPath() . '/mg /mg.class.php'; mg::init();															*
																														*
$equipEcl = '#[Salon][Lampe Couloir]#';																					*
$infNbMvmt = '#[Salon][Mouvement Entrée][Présence]#';																	*
$timer = mg::getParam('Lumieres', 'timerEclCouloir');																	*
																														*
$infEclRdC = '#[Salon][Eclairages][Lampe Générale Etat]#';																*
$cdExtinction = (mg::getVar('NuitSalon') != 1 || mg::getCmd($infEclRdC) == 0);											*
$cdAllumage = '';																										*
																														*
mg::minuterie($equipEcl, $infNbMvmt, $timer, $cdExtinction, $cdAllumage);						 						*
*-----------------------------------------------------------------------------------------------------------------------*
************************************************************************************************************************/
function minuterie($equipEcl, $infNbMvmt, $timer=2, $cdExtinction, $cdAllumage, $actionEtat='Etat') {
//	mg::setCron('', "*/$timer * * * *");
	mg::setCron('', time() + $timer*60);

	$lastMvmt = round(mg::lastMvmt($infNbMvmt, $nbMvmt)/60);
	self::message('', "cdExtinction : $cdExtinction - nbMvmt - $nbMvmt - lastMvmt : $lastMvmt");

	// On allume sur cdAllumage ou NbMvmt
	if (($nbMvmt || $cdAllumage) && !$cdExtinction) {
		// ------------------------------------------------------------------------------------------------------------
		self::messageT('', "ALLUMAGE");
		// ------------------------------------------------------------------------------------------------------------
		if (!self::getCmd($equipEcl, $actionEtat)) {
			$action = str_replace('Etat', 'On', $actionEtat);
			self::setCmd($equipEcl, $action);
		}

	// On éteint si appelé par le cron ou cdExtinction
	} elseif ($cdExtinction || $lastMvmt >= ($timer)) {
		// ------------------------------------------------------------------------------------------------------------
	self::messageT('', "EXTINCTION");
		// ------------------------------------------------------------------------------------------------------------
		if (self::getCmd($equipEcl, $actionEtat) || (self::existCmd($equipEcl, 'Puissance') && self::getCmd($equipEcl, 'Puissance') > 2)) {
			$action = str_replace('Etat', 'Off', $actionEtat);
			self::setCmd($equipEcl, $action);
		}
	}
}

/************************************************************************************************************************
* UTIL													SET LAMPE														*
*************************************************************************************************************************
* Gère le contrôle des lampes en prenant en compte 																		*
*	L'obligation du On  pour celle pourvue d'une commande  Etat_On-Off													*
*	Une attente de dispo de Zwave pour les types openzwave pour éviter la saturation de la queue						*
*	Paramètres :																										*
*		$equipLampe : Le nom de l'équipement de la lampe																*
*		La nouvelle intensité désirée																					*
************************************************************************************************************************/
function setLampe($equipLampe, $intensite) {
	$type = strtolower(mg::getTypeEqui($equipLampe));
	// Si type == openzwave on attend queue Zwave == 0
	if ($type == 'openzwave') { mg::ZwaveBusy(1, 5); }

	// Lampe avec réglage intensité
	$etatLampe = mg::getCmd($equipLampe, 'Etat');
	mg::message('', "Equipement : ".self::toHuman($equipLampe)." - Intensité : $etatLampe ($etatLampe => $intensite) type : $type");
	if (mg::existCmd($equipLampe, 'Slider Intensité')) {
		if ($intensite == 0 && $etatLampe >= 1) {
			mg::setCmd($equipLampe, 'Slider Intensité', $intensite);
			mg::setCmd($equipLampe, 'Off');
		} elseif ($intensite > 0 && $etatLampe != $intensite) {
			//mg::setCmd($equipLampe, 'On');
			mg::setCmd($equipLampe, 'Slider Intensité', $intensite);
		}

	// Lampe SANS réglage intensité
	} else {
		if ($intensite == 0 ) {
			mg::setCmd($equipLampe, 'Off');
		} elseif ($intensite > 0) {
			mg::setCmd($equipLampe, 'On');
		}
	}
}

/************************************************************************************************************************
* UTIL											GET CONFIG JEEDOM														*
*************************************************************************************************************************
* Récupère une config du core																							*
*	Paramètres :																										*
*		$plugin : Le nom du plugin																						*
*		$key La clef recherché ('api' par defaut, 'internalAddr' pour l'ip, etc)										*
************************************************************************************************************************/
function getConfigJeedom($plugin, $key='api') {
	$values = array();
	$sql = "SELECT value FROM `config` WHERE `plugin` = '$plugin' AND `key` = '$key'";
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

	if (count($result[0]) > 0) {
		self::message('', self::$__log_SP . __FUNCTION__ . " : $plugin / $key ==> ".$result[0]['value']);
		return $result[0]['value'];
	} else {
		self::message('', self::$__log_SP . __FUNCTION__ . " : $plugin / $key ==> Aucune valeur de renseignée");
		return null;
	}
}

/************************************************************************************************************************
* UTIL													GET ICS															*
*************************************************************************************************************************
* Renvoi l'agenda ICS de la période demandée sous forme de tableau trié sur le time de début.							*
* Paramètres :																											*
*		$url : chemin (ou URL préfixe de http.... complet de l'agenda ICS												*
*		$start : Jour de début de la période demandée (format timestamp).												*
*		$end : Jour de début de la période demandée (format timestamp).													*
*																														*
*		Tableau de retour : [title], [start], [end], [description], [location]											*
*																														*
* NB !!! Déclaration à mettre en tête de la class :																		*
*		use Sabre\VObject;																								*
*		use ICal\ICal;																									*
*																														*
*		cf https://sabre.io/vobject/icalendar/																			*
************************************************************************************************************************/
	function getICS($url, $start=0, $end=0) {
		if (!$start) { $start = time(); }
		if (!$end) { $end = time(); }

		$start = date('Ymd', $start).'000000';
		$end = date('Ymd', $end).'235959';

		self::message('', self::$__log_SP . __FUNCTION__ . " : Agendas du : " . date('d\/m\/Y \à H\hi\m\n', strtotime($start)). " - au : ". date('d\/m\/Y \à H\hi\m\n', strtotime($end)));

		$datas = array();
		$vcalendar = VObject\Reader::read(fopen($url,'r'));
		$newVCalendar = $vcalendar->expand( new DateTime($start),new DateTime($end));
		$nb = @count($newVCalendar->VEVENT);
self::message('tmp', "************** $nb ---- ");

		for($i = 0; $i < $nb; ++$i) {
			$start = $newVCalendar->VEVENT[$i]->DTSTART;
			$end = $newVCalendar->VEVENT[$i]->DTEND;
			$start = new DateTime($start);
			$end = new DateTime($end);
			array_push($datas, array('start' => date('Y-m-d H:i:s',$start->getTimestamp()),'end'=>date('Y-m-d H:i:s',$end->getTimestamp()),'title'=>(string)$newVCalendar->VEVENT[$i]->SUMMARY ,'description'=>(string)$newVCalendar->VEVENT[$i]->DESCRIPTION , 'location'=>(string)$newVCalendar->VEVENT[$i]->LOCATION));
		}

		if ($nb > 1) $datas = self::tri_Tableau($datas, 'start', SORT_ASC);
		mg::message('', print_r($datas, true));
		if ($nb > 0) return $datas;
	}

/************************************************************************************************************************
* UTIL													SKIP ACCENT														*
* Remplace tous les caractères accentués																				*
************************************************************************************************************************/
function skip_accents( $str, $charset='utf-8' ) {
	$str = htmlentities( $str, ENT_NOQUOTES, $charset );
	$str = preg_replace( '#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str );
	$str = preg_replace( '#&([A-za-z]{2})(?:lig);#', '\1', $str );
	$str = preg_replace( '#&[^;]+;#', '', $str );
	return $str;
}

/************************************************************************************************************************
* UTIL												NETTOIE CHAINE														*
* Remplace tout ce qui est :																							*
*	Retour-chariot (\r)																									*
*	Nouvelle ligne (\n)																									*
*	Tabulation (\t)																										*
*	par du vide ("")																									*
************************************************************************************************************************/
	function nettoieChaine($chaine) {
	$chaine = str_replace("\n" ,"",$chaine);
	$chaine = str_replace("\r" ,"",$chaine);
	$chaine = str_replace("\t" ,"",$chaine);
	return $chaine;
	}

/************************************************************************************************************************
* UTIL												DATE INTERVALLE														*
* Remvoie une chaine formatée de la durée de l'intervalle sous la forme	 "9j 21h 32mn 27s"								*
*	Le nombre de segments de chaine à renvoyer (max 6)																	*
*	La valeur de l'intervalle est dispo en quatrième paramètre															*
************************************************************************************************************************/
function dateIntervalle($depuis, $jusque='now', $nbVal =2, &$diff=0) {
	$jusque = new DateTime($jusque);
	$depuis = new DateTime($depuis);
	$diff = $jusque->diff($depuis);

	$format= '';
	$nb = 1;
	if ($diff->format('%y'))				  { $format .= '%yan '; $nb++; }
	if ($diff->format('%m') && $nbVal >= $nb) { $format .= '%mm '; $nb++; }
	if ($diff->format('%d') && $nbVal >= $nb) { $format .= '%dj '; $nb++; }
	if ($diff->format('%h') && $nbVal >= $nb) { $format .= '%hh '; $nb++; }
	if ($diff->format('%i') && $nbVal >= $nb) { $format .= '%imn '; $nb++; }
	if ($diff->format('%s') && $nbVal >= $nb) { $format .= '%ss '; $nb++; }

	return $diff->format($format);
}

/************************************************************************************************************************
* UTIL														GET DISTANCE												*
*************************************************************************************************************************
* Retourne la distance en metre ou kilometre (si $unit = 'k') entre deux latitude et longitude fournies					*
************************************************************************************************************************/
	function getDistance($lat1, $lng1, $lat2, $lng2, $unit ='k') {
		$earth_radius = 6378137;   // Terre = sphère de 6378km de rayon
		$rlo1 = deg2rad($lng1);
		$rla1 = deg2rad($lat1);
		$rlo2 = deg2rad($lng2);
		$rla2 = deg2rad($lat2);
		$dlo = ($rlo2 - $rlo1) / 2;
		$dla = ($rla2 - $rla1) / 2;
		$a = (sin($dla) * sin($dla)) + cos($rla1) * cos($rla2) * (sin($dlo) * sin($dlo));
		$d = 2 * atan2(sqrt($a), sqrt(1 - $a));
		//
		$meter = ($earth_radius * $d);
		if ($unit == 'k') {
			//self::message('', self::$__log_SP . __FUNCTION__ . " : ($lat1, $lng1, $lat2, $lng2) => Distance calculée $meter m.");
			return floatval($meter / 1000);
		}
		return $meter;
	}

/************************************************************************************************************************
* UTIL														TRANSPOCAP													*
*************************************************************************************************************************
* Transpose un cap géographique donné en Cap/Code et Libellé															*
*	Paramètres :																										*
*		$cap : Le cap recherché sous forme de code(N, NNE, E, ....NNO) ou de cap(0...360)								*
*		Retourne le Code OU le CAP (selon la demande) ainsi que le libellé dans le paramètre $retour					*
************************************************************************************************************************/
	function transpoCap($cap, &$retour = '') {
		$cap = intval($cap);
		$deltaCap = 360 /16 / 2;

		$tabCode = 'N:NNE:NE:ENE:E:ESE:SE:SSE:S:SSO:SO:OSO:O:ONO:NO:NNO:N';
		$tabCap = '0:22:45:67:90:120:145:167:180:202:225:247:270:292:315:337:360';
		$tabLibelCap = 'Nord:Nord Nord Est:Nord Est:Est Nord Est:Est:Est Sud Est:Sud Est:Sud Sud Est:Sud:Sud Sud Ouest:Sud Ouest:Ouest Sud Ouest:Ouest:Ouest Nord Ouest:Nord Ouest:Nord Nord Ouest:Nord';

		$detailsCap = explode(':', $tabCap);
		$detailsCode = explode(':', $tabCode);
		$detailsLibelle = explode(':', $tabLibelCap);

		// Recherche par cap
		if (is_int($cap)) {
			if ($cap <= 0 || $cap > 360) { $cap = 360; }
			for ($i = 0; $i < count($detailsCap); $i++) {
				if ($cap - $deltaCap <= trim($detailsCap[$i])) {
					self::message('', self::$__log_SP . __FUNCTION__ . " : Demande $cap ==> " . $detailsCap[$i] . " - " . $detailsCode[$i] . " - " . $detailsLibelle[$i]);
					$retour = $detailsLibelle[$i];
					return $detailsCode[$i];
				}
			}
		} else {
			// Recherche par Code
			for ($i = 0; $i < count($detailsCode); $i++) {
				if ($cap == trim($detailsCode[$i])) {
					self::message('', self::$__log_SP . __FUNCTION__ . " : Demande $cap ==> " . $detailsCap[$i] . " - " . $detailsCode[$i] . " - " . $detailsLibelle[$i]);
					$retour = $detailsLibelle[$i];
					return $detailsCap[$i];
				}
			}
		}
	}

/************************************************************************************************************************
* UTIL												BENCH SCENARIO														*
*************************************************************************************************************************
* Donne la durée moyenne d'éxécution d'un script donné ou d'une fonction (modif du code nécessaire)						*
*	Paramètres :																										*
*		NbIter : Nombre d'itération de calcul																			*
*		NumScenario : N° du scénario à tester																			*
*																														*
*	Exemple :																											*
*		mg::BenchScenario(10, 137);																						*
*		print_r(array_sort($people, 'age', SORT_DESC)); // Sort tri par le plus vieux en premier						*
*		print_r(array_sort($people, 'nom', SORT_ASC)); // tri par nom ascendant											*
	********************************************************************************************************************/
	function benchScenario($nbIter, $numScenario) {
	  global $scenario;
		$start =  microtime(true);

		for ($i = 0; $i < $nbIter; $i++) {
			self::setScenario($numScenario, 'start');

			// Remplacer la ligne précédente par l'appel de la fonction à bencher
			// exemple : self::message('', "test $i ");
			// Fin de l'appel

		}
		$time =	 round((microtime(true) - $start) / $nbIter, 3) * 1000;
		self::message('', "**************** $time mSec sur $nbIter Itérations du scénario '" . $scenario->byId($numScenario)->getName() . "' *******************");
	}

/************************************************************************************************************************
* UTIL												TRI TABLEAU															*
*************************************************************************************************************************
* Tri un tableau selon une clefs données en maintenant les index associés												*
*	Paramètres :																										*
*		array : Tableau à trier																							*
*		key : clef de tri (N° index ou key)																				*
*		ordre = Ordre de tri : SORT_ASC ou SORT_DESC																	*
* Exemple :																												*
*	 $people = array(																									*
*				12345 => array( 'id' => 12345, 'prenom' => 'Joe',	'nom' => 'Bloggs',	'age' => 23,	'sexe' => 'm'),	*
*				12346 => array( 'id' => 12346, 'prenom' => 'Adam',	'nom' => 'Smith',	'age' => 18,	'sexe' => 'm'),	*
*				12347 => array( 'id' => 12347, 'prenom' => 'Amy',	'nom' => 'Jones',	'age' => 21,	'sexe' => 'f')	*
*				);																										*
*	 print_r(array_sort($people, 'age', SORT_DESC)); // Tri par le plus agé en premier									*
*	 print_r(array_sort($people, 'nom', SORT_ASC)); // Tri par nom ascendant											*
************************************************************************************************************************/
	function tri_Tableau($array, $key, $ordre=SORT_ASC) {
		$new_array = array();
		$sortable_array = array();

		if (count($array) > 0) {
			foreach ($array as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $k2 => $v2) {
						if ($k2 == $key) {
							$sortable_array[$k] = $v2;
						}
					}
				} else {
					$sortable_array[$k] = $v;
				}
			}

			switch ($ordre) {
				case SORT_ASC:
					asort($sortable_array);
				break;
				case SORT_DESC:
					arsort($sortable_array);
				break;
			}

			foreach ($sortable_array as $k => $v) {
				$new_array[$k] = $array[$k];
			}
		}

		return $new_array;
	}

/************************************************************************************************************************
* UTIL												GET LAST FILE														*
*************************************************************************************************************************
* Trouve le fichier le plus récent dans un dossier / sous dossier														*
*	Paramètres :																										*
*		dirPath : Le chemin local du dossier a scanner																	*
*		validExt : Les extensions autorisées (séparées par le caractère '|' si plusieurs)								*
*		$recursive : Mettre à 'true' pour scanner aussi les sous-dossiers												*
*	Exemple :																											*
*		$LastFile = mg::getLastFile(mg::VerifPath(getRootPath() . '/mg'), 'jpg|css|gif|bmp', true);						*
************************************************************************************************************************/
	function getLastFile($dirPath, $validExt, $recursive) {
		$lastfile = self::__getLastFile($dirPath, $validExt, $recursive);
		if ($lastfile[0] > 0) {
			self::Message('', "Fichier le plus récent $lastfile[1] du " . date('d/m/Y H:i:s', $lastfile[0]));
		}
		else {
			self::Message('', "Le dossier $dirPath ne contient aucun fichier");
		}
		return $lastfile[1];

		// ************************************************************************************************************
		 function __getLastFile($dirPath, $validExt, $recursive) {
			$lastMtime = 0;
			$lastFile = null;
			foreach (array_diff(scandir($dirPath), array('..', '.')) as $entry) {
				$file = $dirPath . '/' . $entry;
				if (is_file($file) && (!$validExt || preg_match('/\.(?:' . $validExt . ')$/i', $entry))) {
					$mtime = filemtime($file);
					if ($mtime > $lastMtime) {
						$lastMtime = $mtime;
						$lastFile = $file;
					}
				}
				else if ($recursive && is_dir($file)) {
					$lastSubFile = __getLastFile($file, $validExt, $recursive);
					if ($lastSubFile[0] > $lastMtime) {
						$lastMtime = $lastSubFile[0];
						$lastFile = $lastSubFile[1];
					}
				}
			}
			return array($lastMtime, $lastFile);
		}
	}

/************************************************************************************************************************
* UTIL												TIME BETWEEN														*
*************************************************************************************************************************
* Vérifie qu'une heure se situe à l'intérieur des limites $start et $end (EN TENANT COMPTE DU PASSAGE A 00:00).			*
*	Paramètres au format TimeStamp ou string 'hh:mm' :																	*
*		$time : heure à vérifier.																						*
*		$start : Heure de début.																						*
*		$end : Heure de fin.																							*
*	Retourne true si $time se situe dans l'intervalle demandée (sans prendre le jour en considération).					*
*	ATTENTION : Si Start < End, même de 1 mn, résultat inversé, donc cas à traiter contextuellement.					*
************************************************************************************************************************/
	function TimeBetween($start, $time, $end) {
		$time = date('H:i', $time);
		$start = date('H:i', $start);
		$end = date('H:i', $end);

		if ($start < $end) {
			$result = (($time >= $start) && ($time < $end)) ? 1 : 0;
		} else {
			$result = (($time >= $start) || ($time < $end)) ? 1 : 0;
		}

		self::message('', self::$__log_INF2 . __FUNCTION__ . " : ($start <= $time <= $end) => ($result)");
		return $result;
	}

/************************************************************************************************************************
* UTIL												VERIF PATH															*
*************************************************************************************************************************
* Vérifie et normalise un chemin de fichier																				*
*	Exemple : mg::VerifPath(getRootPath() . '/mg') renvoi : '/var/www/html/mg'											*
************************************************************************************************************************/
	function VerifPath($dirPath) {
		$dirPath = realpath($dirPath);
		if (!$dirPath || !file_exists($dirPath) || !is_dir($dirPath)) {
		  self::Message('', "Le dossier $dirPath n'existe pas.");
		  return null;
		}
		elseif (!is_readable($dirPath)) {
		  self::Message("Le dossier $dirPath n'est pas acessible en lecture.");
		  return null;
		}
		return $dirPath;
	}

/************************************************************************************************************************
* UTIL													STATS_HISTO														*
*************************************************************************************************************************
* Extrait de l'histo d'une commande les valeurs correspondant au paramètres temporel d'entrée.							*
*	Paramètres :																										*
*		$infCmd : La commande dont on veut extraire des infos.															*
*		$result : Type de valeurs à extraire :																			*
												'S' valeur de départ,													*
												'E', valeur de fin,														*
												'D' différence Fin-Debut,												*
												'DB' Durée pendant lequel la cmd était à '1' durant la période).		*
*		$typePeriode : T heure, D Jour, M mois, Y année.																*
*		$deltaStart : Nb de période à soustraire à 'maintenant' pour avoir la date de début (positif ou négatif).		*
*		$nbPeriodes : Nb de périodes séparant la date de début de la date de fin (positif ou négatif).					*
*																														*
*	Exemple :																											*
*		StatsHisto($infCmd, 'D', 'Y', 0, 1); Année courante																*
*																														*
*		StatsHisto($infCmd, 'D', 'M', 0, 12); Les 12 derniers mois														*
*		StatsHisto($infCmd, 'D', 'M', 3, 1); Le mois -3																	*
*																														*
*		StatsHisto($infCmd, 'D', 'D', 0, 1); La dernière journée														*
*		StatsHisto($infCmd, 'D', 'D', 9, 365.25); Dernière année à partir du jour -9									*
*																														*
*		StatsHisto($infCmd, 'D', 'T', 0, 1); La dernière heure															*
************************************************************************************************************************/
	function StatsHisto($infCmd, $result = 'D', $typePeriode = 'M', $deltaStart = 0, $nbPeriodes = 1) {

		if ($typePeriode == 'T' ) // Heure
		{
			$startTime = date('Y-m-d H:i:s', mktime(date("H") - $deltaStart, 0, 0, date("m")  , date("d"), date("Y")));
			$endTime = date('Y-m-d H:i:s', mktime(date("H") - $deltaStart + $nbPeriodes, 59, 59, date("m")	, date("d"), date("Y")));
		}

		elseif ($typePeriode == 'D') // Jour
		{
			$startTime = date('Y-m-d H:i:s', mktime(0, 0, 00, date("m") , date("d") - $deltaStart, date("Y")));
			$endTime = date('Y-m-d H:i:s', mktime(23, 59, 00, date("m") , date("d") - $deltaStart + $nbPeriodes, date("Y")));
		}

	   elseif ($typePeriode == 'M' ) // Mois
		{
			$startTime = date('Y-m-d H:i:s', mktime(0, 0, 00, date("m") - $deltaStart , 1, date("Y")));
			$endTime = date('Y-m-d H:i:s', mktime(23, 59, 00, date("m") - $deltaStart + $nbPeriodes , 0, date("Y")));
		}
		elseif ($typePeriode == 'Y' ) // Année
		{
			$startTime = date('Y-m-d H:i:s', mktime(0, 0, 30, 1, 0, date("Y") - $deltaStart));
			$endTime = date('Y-m-d H:i:s', mktime(23, 59, 30, 13, 0, date("Y") - $deltaStart + $nbPeriodes));
		}

		  //recupère les valeurs de début, de fin et différence de la plage
		$histo = new scenarioExpression();

		$start = intval($histo->MinBetween($infCmd, $startTime, $endTime));
		$end = intval($histo->MaxBetween($infCmd, $startTime, $endTime));
		 $diff = ($end - $start);

		if ($result == 'D') { $resultat = $diff; }
		elseif ($result == 'S') { $resultat = $start; }
		elseif ($result == 'E') { $resultat = $end; }
		elseif ($result == 'DB') { $resultat = $histo->durationbetween($infCmd, 1, $startTime, $endTime);}

		return $resultat;
	}

/************************************************************************************************************************
* UTIL												GET_DELTA_TO_DATE													*
*************************************************************************************************************************
* Extraction de la différence de #value# entre deux dates dans la base SQL												*
*	Paramètres :																										*
*		$_cmd_id		Nom de la commande .																			*
*		$_startTime		Date de début au format 2017-08-01 00:00:00														*
*		$_endTime		Date de fin au format 2017-08-31 23:59:59														*
************************************************************************************************************************/
	function getDeltaToDates($_cmd_id, $_startTime, $_endTime) {
		$_cmd_id = str_replace('#', '', $_cmd_id);

		// Extraction de la valeur à la date min
		$values = array(
			'cmd_id' => $_cmd_id,
			'startTime' => $_startTime,
			'endTime' => $_endTime,
		);
		$sql = 'SELECT *
		FROM (
			SELECT *
			FROM history
			WHERE cmd_id=:cmd_id
			AND `datetime`>=:startTime
			AND `datetime`<=:endTime
			UNION ALL
			SELECT *
			FROM historyArch
			WHERE cmd_id=:cmd_id
			AND `datetime`>=:startTime
			AND `datetime`<=:endTime
		) as dt
		ORDER BY `datetime` ASC
		LIMIT 1';
		$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
		if (!is_array($result)) {
			$result = array();
		}

		// Extraction de la valeur à la date max
		$values = array(
			'cmd_id' => $_cmd_id,
			'startTime' => $_startTime,
			'endTime' => $_endTime,
		);
		$sql = 'SELECT *
		FROM (
			SELECT *
			FROM history
			WHERE cmd_id=:cmd_id
			AND `datetime`>=:startTime
			AND `datetime`<=:endTime
			UNION ALL
			SELECT *
			FROM historyArch
			WHERE cmd_id=:cmd_id
			AND `datetime`>=:startTime
			AND `datetime`<=:endTime
		) as dt
		ORDER BY `datetime` DESC
		LIMIT 1';
		$result2 = DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);
		if (!is_array($result2)) {
			$result2 = array();
		}
		else {
		return ($result2['value'] - $result['value']);
		}
	}

/************************************************************************************************************************
* UTIL													GETPING_IP														*
*************************************************************************************************************************
*	Ping direct d'une IP																								*
************************************************************************************************************************/
function getPingIP($IP, $user='', $nbTentatives=2, $delay=200) {
	$scanIP = shell_exec("sudo ping -c $nbTentatives -t $delay $IP");
	if (strpos($scanIP, '0 received') === false) {
		return true;
	} elseif ($user) {
		mg::message('', "*** Tentative ping sur $user - $IP => INTROUVABLE ***");
	}
}

/************************************************************************************************************************
* UTIL													CRON 2 TIME														*
*************************************************************************************************************************
* Renvoi le timestamp Unix d'une chaine de type "cron".																	*
*	Si le cron n'est pas fourni en paramêtre, on utilise le cron du scénario courant.									*
************************************************************************************************************************/
	function cron2Time($cron = '') {
		if ($cron == '') {
			global $scenario;
			$cron = $scenario->getSchedule();
		}
		$detailsCron = explode(' ', $cron);
		return mktime ($detailsCron[1], $detailsCron[0], 0, $detailsCron[3], $detailsCron[2], $detailsCron[5]);
	}

/************************************************************************************************************************
* UTIL													SHORTER MESSAGE													*
*************************************************************************************************************************
* Réduit la longueur d'un message																						*
************************************************************************************************************************/
	function shorterMessage($message, $longueur=138) {
		return (is_string($message) && strlen($message) > $longueur ? substr($message, 0, $longueur-6) . " (...)" : $message);
	}

/************************************************************************************************************************
* UTIL													MESSAGE	TITRE													*
*************************************************************************************************************************
* Permet d'envoyer facilement des messages au format ;																	*
*					========== SURVEILLANCE DU SERVEUR JEEDOM ======													*
*	Précédé et suivi d'une ligne de $char.																				*
*	La longueur de la ligne est donnée par $longueurLigne																*
*	Le caractère de la ligne est donné par $char																		*
*	Si le message est préfixé par $flagTitre, une ligne vide sera inséré avant											*
*	Si le message est préfixé par $flagMono, les lignes d'encadrement ne sont pas affichés								*
*																														*
*	Paramètres																											*
*	La syntaxe est intégralement reprise la fonction Message															*
*																														*
************************************************************************************************************************/
	function messageT($type, $contenu = '', $titre = 'INFO') {
		$char = '=';
		$flagTitre = '!';
		$flagMono = '.';
		$longueurLigne = 138;

		$message = trim($contenu, $flagTitre);
		$message = trim($message, $flagMono);
		$message = trim($message);

		$widthContenu = strlen($message)+2;
		$widthRem1 = max(intval(($longueurLigne - $widthContenu)/2), 1);
		$widthRem2 = max($longueurLigne - $widthContenu - $widthRem1, 1);

		// Ligne vide
		if ($contenu[0] == $flagTitre) {
			self::Message($type, '.', $titre);
		}
		// Ligne supérieure
		if ($contenu[0] != $flagMono) {
			self::Message($type, str_repeat($char, $longueurLigne), $titre);
		}
		// Ligne de message
		$ligne = str_repeat("=", $widthRem1) . " $message " . str_repeat("=", $widthRem2);
		self::Message($type, $ligne, $titre);
		// Ligne inférieure
		if ($contenu[0] != $flagMono) {
			self::Message($type, str_repeat($char, $longueurLigne), $titre);
		}
	}

// ******************************************* Pour test de niveau de debug *******************************************
	function test() {
		$scenario;
		$scenario->setLog(" debug : $__debug");
		self::message('', __FUNCTION__ . " - Texte banalisé");
		self::message('', self::$__log_INFO . __FUNCTION__ . " Texte info");
		self::message('', self::$__log_SP . __FUNCTION__ . " texte SP");
		self::message('', self::$__log_WARNING . __FUNCTION__ . " Texte warning");
		self::message('', self::$__log_ERROR . __FUNCTION__ . " Texte erreur");
		$scenario->setLog(".");
	}

/************************************************************************************************************************
* UTIL													MESSAGE															*
*************************************************************************************************************************
* Permet d'envoyer des messages unifiés à tout une série de destinations/Destinataires en une fois						*
*																														*
*	Paramètres																											*
*			$type : Série de séquence séparées par une ',' contenant les destinations suivi des destinataires			*
*				les destinations :																						*
*						Log:nomdulog1/nomdulpg2/... Defaut : log du scénario - $titre = Niveau du Log					*
*						SMS:Dest1/@Dest2/0607020842/...			Si préfixé de @ le N° est récupéré dans $tabUser		*
*						MAIL:Dest1/Dest2/...					Destinataires tel que définis dans plugin MAIL			*
*						TTS:SONOS/JPI/GOOGLECAST/FULLYKIOSK		Si préfixé d'un @ envoi du JINGLE - $titre = Volume		*
*						Message									Fenêtre de message de JEEDOM							*
*						SMS_JEEDOM:/Dest1/Dest2/...				Destinataires tel que définis dans ^plugin PhoneMarket	*
*						APPEL_JEEDOM:/Dest1/Dest2/...			Destinataires tel que définis dans ^plugin PhoneMarket	*
*						APPEL_JPI/Dest1/Dest2/...				Appel sans vocalisation !!!								*
*	exemple :																											*
*		mg::Message("Log:/_ALARME, message, SMS/@MG/NR, MAIL:MG, TTS:GOOGLECAST/JPI", 'Message multi destinat', 75);	*
*	Remarques :																											*
*		Champ $type : si vide, simple demande log standard.																*
*		Champ $contenu : Contenu du message																				*
*		Champ $titre peut contenir le volume (pour les TTS_JPI															*
* self::$__debug == 0 pas de log, 1 TOUS les logs, 2 logs infos et +, 3 log warning et +, 4 log error et +				*
************************************************************************************************************************/
	function message($type, $contenu = '', $titre = 'INFO') {
		if (self::$__debug <= 0 ) { return; }
		global $scenario;

		if ($contenu == '') {
			$scenario->setLog(self::$__log_ERROR . __FUNCTION__ . " : Le contenu du message est absent !!!");
			return;
		}

		// Pose du $type par defaut pour le log si absent
		$type = ((trim($type) == ''	 || strpos(strtoupper($type), 'LOG:') === false) ? 'log:x/,' : '') . $type;

		if (strstr($type, ',') == false) { $type .= ','; }
//		 *************** Boucle des Destinations séparé par des ',' ***************
		$listeDestinations = explode(',', $type);
		for ($i = 0; $i < count($listeDestinations); $i++) {

			if (strstr($listeDestinations[$i], ':') == false) { $listeDestinations[$i] .= ':'; }
			// *************** Séparation destination / destinataires par un ':' ***************
			$listeDestinataires = explode(":", $listeDestinations[$i]);

			$destination =	strtoupper(trim($listeDestinataires[0]));
			for ($y = 1; $y < count($listeDestinataires); $y++) {

			if (strstr($listeDestinataires[$y], '/') == false) { $listeDestinataires[$y] .= '/'; }
				// *************** Boucle des destinataires séparé par des '/' ***************
				$destinataires = explode("/", $listeDestinataires[$y]);
				for ($z = 0; $z < count($destinataires); $z++) {
					$destinataire = strtoupper(trim($destinataires[$z]));
					if ($destination == 'LOG' && $z == 0) { $destinataire = 'x'; } // Pour le log par defaut

					// Transpo du destinataire si préfixé de '@'
					if (strpos($destinataire, '@') !== false) {
						$tabUser = self::getVar('tabUser');
						$destinataire = $tabUser['Tel-' . trim(trim($destinataire, '@'))]['tel'];
					}

					if (($destination != 'null' && $destinataire != '') || $destination == 'MESSAGE') {

						// --------------------------------------------- LOG ------------------------------------------
						if ($destination == 'LOG') {
							// Calcul de l'en tête du log à INFO si absent
							if (!strstr($contenu, self::$__log_SP)
								&& !strstr($contenu, self::$__log_ERROR)
								&& !strstr($contenu, self::$__log_WARNING)
								&& !strstr($contenu, self::$__log_INF2)
								&& !strstr($contenu, self::$__log_INFO)) {
								$contenuLog = self::$__log_INFO . $contenu;
							}	else {
								$contenuLog = $contenu;
							}

							// Filtrage selon niveau d'affichage des logs
							$OK = 0;
							if	   (self::$__debug >=1 && strstr($contenuLog, self::$__log_INFO)) { $OK++; }
							elseif (self::$__debug >=2 && strstr($contenuLog, self::$__log_ERROR)) { $OK++; }
							elseif (self::$__debug >=3 && strstr($contenuLog, self::$__log_WARNING)) { $OK++; }
							elseif (self::$__debug >=4 && strstr($contenuLog, self::$__log_INF2)) { $OK++; }
							elseif (self::$__debug >=5 && strstr($contenuLog, self::$__log_SP)) { $OK++; }
							if ($OK == 0) { return; }

							if ($destinataire == 'x') {
								// Si log par défaut uniquement si self::$__debug
								if (self::$__debug) { $scenario->setLog($contenuLog); }
								// Sinon log nommé
							} else {
								log::add($destinataire, $titre, trim($contenu), '@');
							}


						// ---------------------------------------- MESSAGE JEEDOM ------------------------------------
						} elseif ($destination == 'MESSAGE') {
							self::setmessageJeedom(trim($contenu, '@'));

						// -------------------------------------------- SMS JEEDOM ----------------------------------
						} elseif ($destination == 'SMS_JEEDOM') {
							self::setcmd('#[Sys_Comm][Jeedom_SMS]#', $destinataire, $titre, trim($contenu, '@'));

						// -------------------------------------------- APPEL JEEDOM ----------------------------------
						// Passe un appel téléphonique en vocalisant le message. Attention payant !!!
						//Destinataire SANS @ de transposition
						} elseif ($destination == 'APPEL_JEEDOM') {
							self::setcmd('#[Sys_Comm][Jeedom_Appel]#', $destinataire, $titre, trim($contenu, '@'));

						// ------------------------------------------- APPEL_JPI --------------------------------------
						// Passe un appel mais NE VOCALISE PAS LE MESSAGE, peut servir pour renforcer une alerte par SMS ou autres
						} elseif ($destination == 'APPEL_JPI') {
							self::JPI('APPEL_JPI', trim($contenu, '@'), $destinataire);

						// --------------------------------------------- MAIL -----------------------------------------
						} elseif ($destination == 'MAIL') {
							// Transpo du destinataire si préfixé de '@'
							self::setcmd('#[Sys_Comm][Mail]#', $destinataire, trim($contenu, '@'), $titre);

						// ---------------------------------------------- SMS JPI -------------------------------------
						} elseif ($destination == 'SMS') {
							// On n'envoie que si le $destination est absent
//							if (self::getVar($destinataire) . '_OK')<0) { break; }
							self::JPI('SMS', trim($contenu, '@'), $destinataire);

							// ----------------------------------- APPEL TTS sur SONOS, JPI, GOOGLECAST ou FULLYKIOSK ------------------------------
						} elseif ($destination == 'TTS') {
							$message = $contenu;

							// Taille finale du message
							if ($destination != 'TTS')
								{
									$lgSegment = 740;
								} else {
									$lgSegment = 9999;
								}
								// Découpage du message sur les '\n' si nécessaire
							$messagePartiel = $message;
							while (strlen($message) > $lgSegment) {
								$messagePartiel = substr($message, 0, $lgSegment);
								if (strpos($message, "\n") !== false) {
									$lgEnTrop = strlen(strrchr($messagePartiel, "\n"));
									$messagePartiel = substr($message, 0, strlen($messagePartiel) - $lgEnTrop);
								}
								$message = substr($message, strlen($messagePartiel), 99999);
									$scenario->setLog('', $messagePartiel . ' - ' . strlen($messagePartiel) . ' charactères');
								self::$destinataire($destination, $messagePartiel , intval($titre));
							}
							if (isset($messagePartiel)) {
								$scenario->setLog('', $messagePartiel . ' - ' . strlen($messagePartiel) . ' charactères');
								self::$destinataire($destination, $message , intval($titre));
							}
						}
					}
				}
			}
		}
	}

/************************************************************************************************************************
* UTIL													NETTOIE REP														*
*************************************************************************************************************************
* Nettoyage répertoire par masque, ancienneté, volume max, Nb fichiers													*
*	Paramètres :																										*
*		Masque : masque complet (avec le répertoire) des fichiers à traiter (ex : '/var/www/html/mg /Snapshots/*.jpg')	*
*		VolMax : Volume max à conserver en octets, 5 Mo par defaut.														*
*		nbJoursMax : Nb de jours à conserver, par defaut 31.															*
*		NbFiles : Nombre maximum de fichiers à conserver, 9999 pardefaut												*
*																														*
*	exemple : mg::NettoieRep('*.jpg', 5);																				*
*************************************************************************************************************************/
	function NettoieRep($masque, $volMax = 5, $nbJoursMax=31, $nbFiles=999) {
	$cptFiles = 0;
	$cptEffacement = 0;
	$totalSize = 0;

	$files = glob($masque);
	arsort ($files);
	foreach($files as $file) {
		$dateModif = filemtime($file);
		$totalSize += filesize($file);
		$cptFiles ++;
		if ($dateModif < (time() - $nbJoursMax*86400) || $totalSize > $volMax || $cptFiles > $nbFiles) {
			unlink($file);
			$cptEffacement ++;
			$message = "Effacement $file du => ". date('Y-m-d H:i:s', $dateModif) . " - Volume Total : $totalSize - Nb fichiers : $cptFiles";
			self::message('', self::$__log_SP . __FUNCTION__ . $message);
		}
	}
	self::message('', self::$__log_SP . __FUNCTION__ . " - $masque => $cptEffacement fichiers supprimés.");
}

/************************************************************************************************************************
* UTIL													SOLEIL															*
*************************************************************************************************************************
* Renvoi un tableau contenant l'heure de lever du soleil du jour, l'heure de coucher,									*
* l'azymuth du soleil ainsi que son altitude.																			*
* Les clefs du tableau renvoyé sont 'lever', 'coucher', 'azimuth' et 'altitude'											*
*	Paramètres :																										*
*		timestamp : Le timestamp Unix du jour pour lequel l'heure de lever du soleil est donnée.						*
*		format : Constantes pour le paramètre format																	*
*			Constante	Description	Exemple																				*
*				SUNFUNCS_RET_STRING	(par defaut) Retourne le résultat en tant que chaîne de caractères	16:46			*
*				SUNFUNCS_RET_DOUBLE	Retourne le résultat en tant que nombre décimal	16.78243132							*
*				SUNFUNCS_RET_TIMESTAMP Retourne le résultat en tant qu'entier (timestamp)	1095034606					*
*		latitude : Par défaut, c'est le Nord. Passez une valeur négative pour le Sud.									*
*		longitude : Par défaut, c'est l'Est. Passez une valeur négative pour l'Ouest.									*
*		Type : Type d'heure lever/coucher soleil :																		*
*				'civile' (par defaut) : aube/crepuscule civile plus proche du ressenti									*
*				'autre valeur' :  heure du lever/coucher de soleil.														*
*		zenith : Par défaut : 90																						*
*																														*
*	exemple : mg::Soleil(time(), $latitude, $longitude, SUNFUNCS_RET_STRING);											*
*************************************************************************************************************************/
	function Soleil($timestamp, $latitude, $longitude, $format = SUNFUNCS_RET_TIMESTAMP, $type = 'civile', $zenith = 90) {
		if ($type == 'civile') { $zenith += 6; }

		// Calcul lever de soleil
		$decalageHoraire = date('O')/100;
		$lever = date_sunrise($timestamp, $format, $latitude, $longitude, $zenith, $decalageHoraire);
		// Calcul coucher de soleil
		$coucher = date_sunset($timestamp, $format, $latitude, $longitude, $zenith, $decalageHoraire);
		// Calcul Azimuth360 et altitude du soleil
		$t = time();
		list($ra,$dec) = self::sunAbsolutePositionDeg($t);
		list($az, $altitude) = self::absoluteToRelativeDeg($t, $ra, $dec, $latitude, $longitude);
		$altitude = $altitude + self::correctForRefraction($altitude);
		$azimuth360 = $az;
		if (0 > $azimuth360) $azimuth360 = $azimuth360 + 360;
		return array('lever' => $lever, 'coucher' => $coucher, 'azimuth' => $azimuth360, 'altitude' => $altitude);
	} //fin Soleil

		/**************************************************************************************************************
														sunAbsolutePositionDeg
		Return the right ascension of the sun at Unix epoch t.
		**************************************************************************************************************/
		function sunAbsolutePositionDeg($t) {
			$dSec = $t - 946728000;
			$meanLongitudeDeg = fmod((280.461 + 0.9856474 * $dSec/86400),360);
			$meanAnomalyDeg = fmod((357.528 + 0.9856003 * $dSec/86400),360);
			$eclipticLongitudeDeg = $meanLongitudeDeg + 1.915 * sin(deg2rad($meanAnomalyDeg)) + 0.020 * sin(2*deg2rad($meanAnomalyDeg));
			$eclipticObliquityDeg = 23.439 - 0.0000004 * $dSec/86400;
			$sunAbsY = cos(deg2rad($eclipticObliquityDeg)) * sin(deg2rad($eclipticLongitudeDeg));
			$sunAbsX = cos(deg2rad($eclipticLongitudeDeg));
			$rightAscensionRad = atan2($sunAbsY, $sunAbsX);
			$declinationRad = asin(sin(deg2rad($eclipticObliquityDeg)) * sin(deg2rad($eclipticLongitudeDeg)));
			return array(rad2deg($rightAscensionRad), rad2deg($declinationRad));
		}

		/**************************************************************************************************************
														absoluteToRelativeDeg
		Convert an object's RA/Dec to altazimuth coordinates.
		http://answers.yahoo.com/question/index?qid=20070830185150AAoNT4i
		http://www.jgiesen.de/astro/astroJS/siderealClock/
		**************************************************************************************************************/
		function absoluteToRelativeDeg($t, $rightAscensionDeg, $declinationDeg, $latitude, $longitude) {
			$dSec = $t - 946728000;
			$midnightUtc = $dSec - fmod($dSec,86400);
			$siderialUtcHours = fmod((18.697374558 + 0.06570982441908*$midnightUtc/86400 + (1.00273790935*(fmod($dSec,86400))/3600)),24);
			$siderialLocalDeg = fmod((($siderialUtcHours * 15) + $longitude),360);
			$hourAngleDeg = fmod(($siderialLocalDeg - $rightAscensionDeg),360);
			$altitudeRad = asin(sin(deg2rad($declinationDeg))*sin(deg2rad($latitude)) + cos(deg2rad($declinationDeg)) * cos(deg2rad($latitude)) * cos(deg2rad($hourAngleDeg)));
			$azimuthY = -cos(deg2rad($declinationDeg)) * cos(deg2rad($latitude)) * sin(deg2rad($hourAngleDeg));
			$azimuthX = sin(deg2rad($declinationDeg)) - sin(deg2rad($latitude)) * sin($altitudeRad);
			$azimuthRad = atan2($azimuthY, $azimuthX);
			return array(rad2deg($azimuthRad), rad2deg($altitudeRad));
		}

		/**************************************************************************************************************
												correctForRefraction
		Return altitude correction for altitude due to atmospheric refraction.
		http://en.wikipedia.org/wiki/Atmospheric_refraction
		**************************************************************************************************************/
		function correctForRefraction($d) {
			if (!($d > -0.5))	   $d = -0.5;  // Function goes ballistic when negative.
			return (0.017/tan(deg2rad($d + 10.3/($d+5.11))));
		}

/************************************************************************************************************************
* Util													Last MOUVEMENT													*
*************************************************************************************************************************
* renvoie la durée depuis le dernier mouvement																			*
* Paramétres :																											*
	$infNbMvmt : La commande de mouvement à surveiller																	*
*	$nbMvmt : Retourne le nbMvmt de la commande																			*
************************************************************************************************************************/
function lastMvmt($infNbMvmt, &$nbMvmt) {
	$nbMvmt = max(0, mg::getCmd($infNbMvmt));
	$lastMvmt = mg::getCond("lastChangeStateDuration($infNbMvmt, 0)");
	$lastMvmt = ($nbMvmt > 0) ? 0 : $lastMvmt;
	self::message('', self::$__log_INF2 . __FUNCTION__ . " : LastMvmt : '".round($lastMvmt/60)."' mn - nbMvmt : '$nbMvmt'");
	return $lastMvmt;
}

/************************************************************************************************************************
* Util														FRAME TV													*
*************************************************************************************************************************
* Gère frame TV, nécessite les plugins SmartThings et TvDomSamsung ainsi qu'un équipement On/Off avec des équipements 	*
	préfixé en conséquence (cf le code plus bas) 																		*
* Paramétres :																											*
*	$nom : Racine du nom des équipements 'Frame TV'																		*
*	$zone : Zone des équipements 'Salon'																				*
*	$action : Action à effectuée : oof, on, hdmi, art																	*
************************************************************************************************************************/
function frameTV($nom, $zone, $action='on') {
	$equipSmartThings = "#[$zone][$nom"."_SmartThings]#";
	$equipTvDomSamsung = "#[$zone][$nom"."_TvDomSamsung]#";
	$equipOnOff = "#[$zone][$nom"."_OnOff]#";

	// OFF
	if ($action == 'off') {
		mg::setCmd($equipSmartThings, 'Eteindre');
		sleep(2);
		mg::setCmd($equipOnOff, 'off');
	// ON ++
	} else {
		if (!mg::getCmd($equipOnOff, 'Etat')) {
			mg::setCmd($equipOnOff, 'on');
			sleep(2);
		}
		mg::wakeOnLan($nom);
			sleep(2);
		mg::setCmd($equipSmartThings, 'Allumer');
		mg::setCmd($equipSmartThings, 'Rafraîchir');
		sleep(2);
		// ART
		if ($action == 'art' /*|| $action == 'on'*/) {
			mg::setCmd($equipTvDomSamsung, 'Sendkey', 'KEY_POWER');
		// HDMI
		} elseif ($action == 'hdmi') {
			mg::setCmd($equipSmartThings, 'Changer de source dentrée', 'HDMI1');
		}
	}
	self::messageT('', '! '.self::$__log_SP . __FUNCTION__ . " : $nom de $zone est à '$action'");
}

/************************************************************************************************************************
* Util														DEBUG														*
*************************************************************************************************************************
* Change le niveau de l'affichage du log debug (1-4), par defaut 3														*
************************************************************************************************************************/
	function debug($debug='', $mode = 'Manuel') {
		global $scenario;

		self::$__debug = $debug;
		$message = ($debug<=0) ? 'Aucun log' : '';
		$message .= ($debug>=1) ? ' + INFO' : '';
		$message .= ($debug>=2) ? ' + ER_ROR': ''; // Le '_' pour éviter le feedback avec la surveillance des logs
		$message .= ($debug>=3) ? ' +  WARNING' : '';
		$message .= ($debug>=4) ? ' +  INF2' : '';
		$message .= ($debug>=5) ? ' + SP': '';
		$message = " CLASS MG - Mode $mode - AVEC debug ($debug) : log => $message.";
		$message = str_repeat("=", (138-strlen($message))/2).$message.str_repeat("=", (138-strlen($message))/2);
		if ($debug > 1) { $scenario->setlog($message); }
	}

/************************************************************************************************************************
*																														*
*												FONCTIONS SUR LES SENARIOS												*
*																														*
************************************************************************************************************************/
function FONCTIONS_SCENARIOS(){}

/************************************************************************************************************************
* Scenario												STOP SCENARIO													*
*************************************************************************************************************************
* Stoppe le scénario en cours (ainsi que le bloc code en cours d'execution).											*
************************************************************************************************************************/
	function stopScenario() {
		self::message('', self::$__log_SP . __FUNCTION__ . " : Arrêt du scénario courant");
		self::__action('stop');
		throw new Exception(self::__getStopException());
	}

/************************************************************************************************************************
* Scenario												GET SCENARIO													*
*************************************************************************************************************************
* renvoie l'état du scénario :																							*
*	1 en cours, 0 si arreté, 1 si desactivé, 2 si le scénario n’existe pas, 3 si l’état n’est pas cohérent				*
*	En outre, retourne dans $name le nom du scénario																	*
************************************************************************************************************************/
	function getScenario($scenario_id, &$name='') {

		$actionScenario = scenario::byId($scenario_id);
		if (!is_object($actionScenario)) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Action sur scénario impossible, le scénario avec l'id $scenario_id est introuvable.");
		} else {
			$etat =	 self::getExp('scenario(' . $scenario_id . ')');
			$name = self::extractPartCmd($actionScenario->getHumanName(), 3);
			self::message('', self::$__log_SP . __FUNCTION__ . " : Le Scénario '$name' (N° $scenario_id) est à l'état '$etat'.");
			return $etat;
		}
	}

/************************************************************************************************************************
* Scenario												SET SCENARIO													*
*************************************************************************************************************************
* Permet le controle des scénarios																						*
*	 - $scenario_id : id L'id du scénario à controler																	*
*	 - $action : action à effectuer: 'start', 'startsync', 'stop', 'deactivate', 'activate', 'resetRepeatIfStatus'		*
*	 - $tags : Permets d’envoyer des tags au scénario, ex: 'montag=2' (uniquement valable avec les actions 'start' et	*
*		'startsync')																									*
* Exemple : mg::scenario(3, 'startsync', 'tag_1=oui tag_2=non');														*
************************************************************************************************************************/
	function setScenario($scenario_id, $action, $tags = '') {
		$scenario_id = intval($scenario_id);
		if (is_int($scenario_id) && $scenario_id > 0) {
			$actionScenario = scenario::byId($scenario_id);
			if (!is_object($actionScenario)) {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : Action sur scénario impossible, le scénario avec l'id $scenario_id est introuvable.");
				return false;
			}
			$action = strtolower(trim($action));
			$actions = array('start', 'startsync', 'stop', 'deactivate', 'activate', 'resetRepeatIfStatus');
			if (!in_array($action, $actions)) {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : L''action '$action' n'est pas valide (actions valides " . implode(", ", $actions) . ")");
				return false;
			}
			if (is_array($tags)) {
				$reformat_tags = array();
				foreach ($tags as $tag => $value) {
				  $reformat_tags['#' . trim(trim($tag), '#') . '#'] = $value;
				}
				$tags = $reformat_tags;
			}
			$startAction = (substr($action, 0, 5) == 'start');

			self::message('', self::$__log_INF2 . __FUNCTION__ . " : Scénario " . $actionScenario->getHumanName() . ' | Action = ' . $action .
							(($startAction && $tags) ? ' | Tags = ' . (is_array($tags) ? json_encode($tags) : $tags) : ''));

			$return = self::__action('scenario', array("scenario_id" => $actionScenario->getId(), "action" => $action, "tags" => $tags));
			if ($startAction) {
				self::message('', self::$__log_INF2 . __FUNCTION__ . " : $return");
				return $return;
			}
			else {
				return true;
			}
		}
		else {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : L'id du scenario $scenario_id n'est pas un nombre valide");
			return false;
		}
	}

/************************************************************************************************************************
* Scenario													SET TAG														*
*************************************************************************************************************************
* Permet d’ajouter/modifier un tag de scénario (le tag n’existe que pendant l’exécution en cours du scénario)			*
*	 Paramètre :																										*
		$tag : Le nom du tag (avec ou sans les '#' autour)																*
		$value : La valeur à affecter au tag																			*
************************************************************************************************************************/
	function setTag($tag, $value = '') {
		$tag = trim($tag, ' #');
		if (!$tag) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le nom du tag ne peut pas être vide");
			return false;
		}
		self::message('', self::$__log_SP . __FUNCTION__ . " : Valeur du tag #" . $tag . '#');
		self::__action('tag', array("name" => $tag, "value" => $value));
		return true;
	}

/************************************************************************************************************************
* Scenario												SCENARIO RETURN													*
*************************************************************************************************************************
* Texte ou une valeur de retour du scénario (pour une interaction par exemple)											*
*	 exemple mg::Scenario_return('Ok, je ferme les volets du salon');													*
************************************************************************************************************************/
	function scenario_return($message) {
		global $scenario;
		$message = trim($message);
		if (!$message && $message != '0') {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le message ne peut pas être vide.");
		  return false;
		}
		self::__action('scenario_return', array("message" => $message));
		self::message('', self::$__log_SP . __FUNCTION__ . " : Retour " . $scenario->getReturn());
		return true;
	}

/************************************************************************************************************************
* Scenario													DEL LOG														*
*************************************************************************************************************************
* Vide le Efface le scénario courant si pas d'error et param 'del' vide.												*
************************************************************************************************************************/
	function delLog($del='') {
	global $scenario;
	$scenarioID = $scenarioID = $scenario->getID();
	$fileLog = "/var/www/html/log/scenarioLog/scenario$scenarioID.log";
		$error = @shell_exec("sudo grep -rn -o -i 'error' $fileLog --files-with-matches");
		if (!$error) {
			shell_exec("sudo rm -f $fileLog");
		}
}

/************************************************************************************************************************
*																														*
*													FONCTIONS JEEDOM													*
*																														*
************************************************************************************************************************/
function FONCTIONS_JEEDOM(){}

/************************************************************************************************************************
* Jeedom												SET CRON														*
*************************************************************************************************************************
* Pose d''un cron sur un scénario																						*
*	Paramètres :																										*
*	 - le N° du $Scénario à impacter (si absent, N° du scénario appelant)												*
*	 - le TimeStamp (ou un masque classique du Cron de type "* /5 * * * * *") du lancement demandé						*
*																														*
*	Le cron de $Scenario sera positionné à la valeur demandé.															*
************************************************************************************************************************/
	function setCron($scenarioID = '', $cron) {
		global $scenario;

		// Calcul du ScenarioID par defaut = celui de l'appelant
		if ( $scenarioID == '' ) { $scenarioID = $scenario->getID(); }

		$scenarioClock = scenario::byId($scenarioID);
		if ( is_object($scenarioClock) ) {
			if (is_numeric($cron)) {
				// Pose du cron
				$scenarioClock->setSchedule(date('i', $cron) . ' ' . date('H', $cron) . ' ' . date('d', $cron) . ' ' . date('m', $cron) . ' * ' . date('Y', $cron));
				self::message('', self::$__log_SP . __FUNCTION__ . " : Le cron du scénario $scenarioID à été positionné au " . date('d/m/Y \à H:i:00', $cron));
			} else {
				$scenarioClock->setSchedule($cron);
			self::message('', self::$__log_INF2 . __FUNCTION__ . " : Le cron du scénario $scenarioID à été positionné sur $cron");
			}
			$scenarioClock->save();
		}
	}

/************************************************************************************************************************
* Jeedom											SET CMD WAIT														*
*************************************************************************************************************************
* Spécifie le mode d'exécution des commandes (attendre (true par defaut) ou non)										*
* Equivalent au réglage "Enchainer les commandes sans attendre" à la différence que le réglage est activable et			*
* désactivable à souhait																								*
* Utile par exemple pour les commandes qui lancent des requêtes http et dont la réponse ne nous importe pas, si le		*
* destinataire ne répond pas, le scénario est bloqué le temps du timeout												*
* En utilisant cette fonction avec *false* en paramètre, le scénario continu à s'exécuter sans attendre la réponse de	*
* la requette http																										*
* example : //Enchaine les commandes sans attendre																		*
*			mg::setCmdWait(false);																						*
*			//Parle dans le Salon																						*
*			mg::setCmd('#[Salon][Script TTS][Speak]#', 'Il fait beau aujoud\'hui');										*
*			//...																										*
*			mg::setCmd('#[Salon][Script TTS][Speak]#', 'C\'est cool !');												*
*			//Enchaine à nouveau les commandes en attendant le retour :													*
*			mg::setCmdWait(true);																						*
 ***********************************************************************************************************************/
	function setCmdWait($wait=true) {
		if (!is_bool($wait)) {
			self::message('', self::$__log_SP . __FUNCTION__ . " : Veuillez préciser le paramètre \$wait (true ou false)");
			return;
		}
		if (!$wait) {
			self::message('', self::$__log_SP . __FUNCTION__ . " : Les commandes éxécutées via setCmd() seront maintenant exécutées sans attendre la réponse");
		} else {
			self::message('', self::$__log_SP . __FUNCTION__ . " : Les commandes éxécutées via setCmd() seront maintenant exécutées normalement");
		}
		self::_setCmdWait($wait);
	}

/************************************************************************************************************************
* Jeedom											DECLENCHEUR															*
*************************************************************************************************************************
* Si $cmd est spécifié, permet de vérifier si c’est bien la valeur en paramètre qui a déclenché le scénario				*
* Sinon si $part est spécifié, renvoie directement la nième $part de commande (1 à 3)									*
* Sinon si ni $cmd ni $part spécifiés, renvoie directement le huamanReadable du déclencheur								*
* Paramètre :																											*
*	$cmd :	La commande à tester Id ou tag de la commande, avec ou sans les '*#*' autour								*
*			Ou bien le nom du déclencheur si ce n'est pas une commande													*
*			Ou bien une chaine devant être contenue dans le déclencheur													*
************************************************************************************************************************/
	function Declencheur($cmd = '', $part='') {
		global $scenario;
		$trigger = $scenario->getRealTrigger();
		$declencheur = self::_cmdToHumanReadable($trigger);

		$cmd = trim($cmd);
		if ($cmd) {
			if (self::_humanReadableToCmd($cmd) == $trigger || strpos($declencheur, $cmd) !== false) {
				self::message('', self::$__log_SP . __FUNCTION__ . " : Le déclencheur contient  '$cmd' (true)");
				return true;
			} else {
				self::message('', self::$__log_SP . __FUNCTION__ . " : Le déclencheur '$declencheur' ne contient PAS '$cmd' (false)");
				return false;
			}
		} else if ($part) {
			return self::extractPartCmd($declencheur, $part);
		} else {
			return $declencheur;
		}
	}

/************************************************************************************************************************
*																														*
*												FONCTIONS SUR LES COMMANDES												*
*																														*
************************************************************************************************************************/
function FONCTIONS_COMMANDES(){}

/************************************************************************************************************************
* Jeedom											CONFIG EQUILOGIC													*
*************************************************************************************************************************
* Positionne dans 'configuration' d'un equiLogic une variable à $newValue ou renvoie sa valeur							*
* Si $newValue non renseignée, renvoi la valeur actuelle:																*
*																														*
* Paramètres :																											*
* $typeName : nom de la class (plugin)																					*
* $equipement : nom de l'équipement OU son HumanName (deux segments)													*
* $name : Nom de la valeur ciblée dans 'configuration'																	*
* $newValue : valeur à écrire, si absent la routine renvoie la value actuelle											*
																														*
* Exemple1 : mg::ConfigEquiLogic, 'Cam_DoorBird', 'ip', $camIP)															*
* Exemple2 : mg::ConfigEquiLogic('rfxcom', 'Porte Comm Chambre Ouest', 'battery_warning_threshold', 55)					*
/***********************************************************************************************************************/
function ConfigEquiLogic($typeName, $equipement, $name, $newValue='') {
	foreach ($typeName::byType($typeName) as $equiLogic) {
		$equiName = $equiLogic->getName();
		$humanName = $equiLogic->getHumanName();
		if ($equiName == $equipement || $humanName == $equipement) {
		$oldValue = $equiLogic->getConfiguration($name);

			if (!$newValue) {
				self::message('', self::$__log_SP . __FUNCTION__ . " : $typeName - $humanName/'$equipement' de '$name' == $oldValue");
				return $oldValue;
			}
			if ($oldValue != $newValue) {
				$equiLogic->setConfiguration($name, $newValue);
				$equiLogic->save();
				self::message('', self::$__log_SP . __FUNCTION__ . " : Modif $typeName - '$equipement' de '$name' : $oldValue => $newValue");
			} else {
				self::message('', self::$__log_SP . __FUNCTION__ . " : $typeName - '$equipement' de '$name' : $oldValue Valeur inchangée !!!");
			}
		}
	}
}

/************************************************************************************************************************
* Jeedom												getMinMaxCmd													*
*************************************************************************************************************************
* Positionne les min et max de la commande																				*
************************************************************************************************************************/
	function getMinMaxCmd($cmd, $complement, $min_max='max') {
//		mg::message('', "$0cmd, $complement");
		if ($complement) { $cmd = self::mkCmd($cmd, $complement); }
//		mg::message('', "1 $cmd, $complement");

		$cmd = self::_tag($cmd);
		if (!$cmd) {
			return null;
		}


		$cmd = cmd::byString($cmd);
		return $cmd->getConfiguration($min_max.'Value');
	}

/************************************************************************************************************************
* Jeedom												setMinMaxCmd													*
*************************************************************************************************************************
* Positionne les min et max de la commande :																			*
************************************************************************************************************************/
	function setMinMaxCmd($cmd, $complement, $min, $max) {
		if (trim($complement) != '') { $cmd = self::mkCmd($cmd, $complement); }
		$cmd_ = cmd::byString($cmd);
		$cmd_->setConfiguration('maxValue',$max);
		$cmd_->save();
		$cmd_->setConfiguration('minValue',$min);
		$cmd_->save();
	}

/************************************************************************************************************************
* Jeedom												MAKE CMD														*
*************************************************************************************************************************
* Renvoi le nom complet d'une commande avec les # encadrant à partir des paramètres :									*
	$equipement avec ou sans #																							*
	$action : Commande avec ou sans les []																				*
************************************************************************************************************************/
	function mkCmd($equipement, $action) {
		if ($action) {
			$equipement = trim(trim(self::ToHuman(trim($equipement)), '#'));
			$action = trim(trim($action), '#');
			$result = '#'.$equipement."[$action]"."#";
		} else { $result = $equipement; }
		return $result;
	}

/************************************************************************************************************************
* Jeedom												GET INFO CMD													*
*************************************************************************************************************************
* Récupère la commande de type *info* associée à une commande de type *action*											*
* $cmd Id ou tag de la commande de type *action*, avec ou sans les '*#*' autour											*
* Si vous utilisez l'id, vous pouvez l'entrer indifféremment avec ou sans les tag '*#*'									*
* Exemple :																												*
* $cmd_On = '#[Salon][Lumière][On]#'; // Commande On de la lampe														*
* $cmd_Etat = $sc->getInfoCmd($cmd_On); // Trouve la commande d'état associée											*
************************************************************************************************************************/
	function getInfoCmd($cmd) {
		$cmd = self::_tag($cmd);
		if (!$cmd) {
			return false;
		}
		$cmd_obj = self::_cmdbyString($cmd);
		if ($cmd_obj) {
			$type = $cmd_obj->getType();
			$cmd = (self::_isId($cmd)) ? self::_tag($cmd_obj->getHumanName()) : $cmd;
			if ($type == 'info') {
				self::message('', self::$__log_SP . __FUNCTION__ . " : La commande $cmd est déjà de type info, utiliser getCmd() pour récupérer sa valeur");
				return null;
			}
			self::message('', self::$__log_SP . __FUNCTION__ . " : Recherche la commande info associée à la commande $cmd");
			$cmd_id = $cmd_obj->getValue();
			if (is_numeric($cmd_id) && intval($cmd_id) > 0) {
				$cmd_obj = self::_cmdbyString($cmd_id);
				if ($cmd_obj) {
					$cmd = self::_tag($cmd_obj->getHumanName());
					self::message('', self::$__log_SP . __FUNCTION__ . " : Commande associée trouvée $cmd");
					return $cmd;
				}
			}
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Aucune commande de type info associée à la commande $cmd");
			return null;
		}
		else {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver la commande$cmd");
			return null;
		}
	}

/************************************************************************************************************************
* Jeedom											GET EQUIPEMENT														*
*************************************************************************************************************************
* Récupère l'équipement (eqLogic) d'une commande (au format #tag#)														*
* $cmd : string $cmd Id ou tag de la commande, avec ou sans les '#' autour.												*
* Si vous utilisez le tag, privilégiez le format #tag# (avec les '#' autour), cela vous permettra de renommer			*
* votre équipement et / ou vos commandes sans avoir à modifier le code du scénario.										*
* Si vous utilisez l'id, vous pouvez l'entrer indifféremment avec ou sans les tag '#'									*
************************************************************************************************************************/
	function getEquipement($cmd) {

		$cmd = self::_tag($cmd);
		if (!$cmd) {
			return null;
		}
		$cmd_obj = self::_cmdbyString($cmd);
		if ($cmd_obj) {
			$cmd = (self::_isId($cmd)) ? self::_tag($cmd_obj->getHumanName()) : $cmd;
			$eqLogic_obj = $cmd_obj -> getEqLogic();
			if (!is_object($eqLogic_obj)) {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : L'Equipement de la commande $cmd est introuvable");
				return null;
			}
				$eqLogic = self::_tag($eqLogic_obj->getHumanName());
				self::message('', self::$__log_SP . __FUNCTION__ . " : Equipement de la commande $cmd ==> $eqLogic");
				return $eqLogic;
		} else {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver la commande $cmd");
			return null;
		}
	}

/************************************************************************************************************************
* Jeedom												PAUSE															*
*************************************************************************************************************************
* Fait une pause de x seconde(s).																						*
*	Il est possible d'utilisé un chiffre décimal en paramètre si le temps de pause est inférieure à 1 seconde.			*
************************************************************************************************************************/
	function pause($duration) {
		self::message('', self::$__log_SP . __FUNCTION__ . " : Pause de $duration seconde(s)");
		if ($duration < 1) {
			if ($duration < 0.000001) { $duration = 0.000001; }
			usleep($duration * 1000000);
		} else {
			sleep(intval(round($duration)));
		}
	}

/************************************************************************************************************************
* Jeedom											SET EQUIPEMENT														*
*************************************************************************************************************************
* Permet le controle des équipements ('show', 'hide', 'deactivate', 'activate')											*
*	param string $eqLogic Id ou nom de l'équipement, avec ou sans les '#' autour										*
************************************************************************************************************************/
	function setEquipement($eqLogic, $action) {
		$eqLogic = self::_tag($eqLogic);
		if (!$eqLogic) {
			return;
		}
		$eqLogic_obj = eqLogic::byId(str_replace(array('#eqLogic', '#'), '', self::_humanReadableToEqLogic($eqLogic)));
		if (!is_object($eqLogic_obj)) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Equipement $eqLogic introuvable");
			return false;
		}
		$action = strtolower($action);
		$actions = array('show', 'hide', 'deactivate', 'activate');
		if (!in_array($action, $actions)) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : L'action n'est pas valide (actions valides : " . implode(", ", $actions));
			return false;
		}
		self::message('', self::$__log_INF2 . __FUNCTION__ . " : Equipement " . self::_tag($eqLogic_obj->getHumanName()) . " ==> $action");
		self::__action('equipement', array("eqLogic" => $eqLogic_obj->getId(), "action" => $action));
	}

/************************************************************************************************************************
* Jeedom												EXIST CMD														*
*************************************************************************************************************************
* Renvoi le type d'objet la commande si elle existe, null sinon															*
*	Paramètres :																										*
*		Nom de la commande complète OU nom de l'équipement ET de la commande à vérifier									*
*		Renvoi l'ID de la commande ainsi que son type dans le paramètre $type											*
************************************************************************************************************************/
	function existCmd($cmd, $complement='', &$type='') {

		if ($complement) { $cmd = self::mkCmd($cmd, $complement); }

		$cmd = self::_tag($cmd);
		if (!$cmd) {
			$type = "'$cmd' Introuvable";
			return null;
		}
		$cmd_obj = self::_cmdbyString($cmd);
		if ($cmd_obj) {
			$cmd = (self::_isId($cmd)) ? self::_tag($cmd_obj->getHumanName()) : $cmd;
			$objet = $cmd_obj->getType();
			if ($objet != null) {
				//self::message('', self::$__log_SP . __FUNCTION__ . " : La commande '$cmd' est de type '$objet'");
				$type = $objet;
				return $cmd_obj->getId();
			}
		}else {
			self::message('', self::$__log_SP . __FUNCTION__ . " : La commande '$cmd' n'éxiste pas");
			$type = 'Introuvable';
			return;
		}
	}

/************************************************************************************************************************
* Jeedom												SET INF															*
*************************************************************************************************************************
* Force la valeur d'une commande info (permet éventuellement de se passer de la commande 'action' associée.				*
*	Paramètres :																										*
*		$cmd : Nom de la commande virtuelle																				*
*		$value : Valeur à affecter au virtuel																			*
************************************************************************************************************************/
	function setInf($cmd, $complement='', $value='') {
		// mg::message('Log:/_TEST', "cmd : '$cmd' - complement : '$complement' - value - $value");
		if (trim($complement) != '') { $cmd = self::mkCmd($cmd, $complement); }
		$cmd = self::_tag($cmd);
		if (!$cmd) {
			return null;
		}
		$cmd_obj = self::_cmdbyString($cmd);
		if ($cmd_obj) {
			$cmd = (self::_isId($cmd)) ? self::_tag($cmd_obj->getHumanName()) : $cmd;
			if ($cmd_obj->getType() != 'info') {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : Cette commande n'est pas de type info, utiliser setCmd() pour l'exécuter");
				return;
			}
		}
		else {
			$value = null;
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver la commande $cmd");
			return;
		}
		$valueAff = self::shorterMessage($value);
		self::message('', self::$__log_INF2 . __FUNCTION__ . " : Forçage à '$valueAff' de la commande $cmd");
		$cmd_obj->event(($value));
	}

/************************************************************************************************************************
* Jeedom												GET CMD															*
*************************************************************************************************************************
* Récupère la valeur d'une commande de type *info*																		*
*	Paramètres :																										*
*		Nom de la commande ou de l'équipement seulement																	*
*		Nom de l'action de la commande si param1 = Equipement, rien si Param1 est la commande complète					*
*		Variable de retour $collectDate et $valueDate																	*
************************************************************************************************************************/
	function getCmd($cmd, $complement = '', &$collectDate = '', &$valueDate = '') {

		if ($complement) { $cmd = self::mkCmd($cmd, $complement); }

		$cmd = self::_tag($cmd);
		if (!$cmd) {
			return null;
		}
		$cmd_obj = self::_cmdbyString($cmd);
		if ($cmd_obj) {
			$cmd = (self::_isId($cmd)) ? self::_tag($cmd_obj->getHumanName()) : $cmd;
			if ($cmd_obj->getType() != 'info') {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : Cette commande n'est pas de type info, utiliser setCmd() pour l'exécuter");
				return null;
			}

			$value = $cmd_obj->execCmd();
			$valueAff = self::shorterMessage($value);
			$collectDate = $cmd_obj->getCollectDate();
			$valueDate = $cmd_obj->getValueDate();
			self::message('', self::$__log_SP . __FUNCTION__ . " : Info de $cmd == ($valueAff) - CollectDate : $collectDate - valueDate : $valueDate");
			$collectDate = strtotime($collectDate);
			$valueDate = strtotime($valueDate);
		}
		else {
			$value = null;
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver la commande $cmd");
		}
		return $value;
	}

/************************************************************************************************************************
* Jeedom												SET CMD															*
*************************************************************************************************************************
* Exécute une commande de type *action*																					*
*	Paramètres :																										*
*		$cmd : Nom de la commande ou de l'équipement seulement															*
*		$complement : Nom de l'action de la commande si param1 = Equipement, rien si Param1 est la commande complète	*
*		$value : La valeur à affecter à la commande (uniquement pour les commandes de sous-type *slider*, *color*,		*
*		*message*  ou *select*)																							*
*		Il est possible de passer une chaine au format *expression jeedom* qui sera calculée.							*
*		Pour les commandes de sous-type *select* la valeur peut être le texte associé à la valeur ou bien directement	*
*		la valeur elle même (non sensible à la casse).																	*
* Retour : *true* si l'opération réussie ou *false* si une erreur survient												*
* Exemple : mg::setCmd('#[MEDIA][FREE SMS][Mon Tel]#', 'Mon message', 'Mon titre');										*
************************************************************************************************************************/
	function setCmd($cmd, $complement = '', $value = '', $title = '') {
		if ($complement) { $cmd = self::mkCmd($cmd, $complement); }
		$cmd = self::_tag($cmd);
		if (!$cmd) {
			return false;
		}
		$cmd_obj = self::_cmdbyString($cmd);
		if ($cmd_obj) {
			$type = $cmd_obj->getType();
			$cmd = (self::_isId($cmd)) ? self::_tag($cmd_obj->getHumanName()) : $cmd;
			if ($type == 'info') {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : La commande '$cmd' est de type info, utiliser getCmd() pour récupérer sa valeur");
				return false;
			}
			self::message('', self::$__log_INF2 . __FUNCTION__ . " : Exécution d'une commande $cmd".(self::_getCmdWait() ? "" : " (sans attendre), ")." de type $type");

			$type = $cmd_obj->getSubtype();
			$options = array();
			$logOptions = '';

			if ($type == 'slider') {
				$options['slider'] = self::getExp($value, false);
				if (!is_numeric($options['slider'])) {
					//self::message('', self::$__log_ERROR . __FUNCTION__ . " : La valeur ".$options['slider']." n'est pas un nombre valide !");
				}
				$logOptions = '	 | options: ( [slider] => ' . $options['slider'] . ' )';

			} elseif ($type == 'message') {
				$options['title'] = self::toHuman(self::getExp($title));
				$options['message'] = self::toHuman(self::getExp($value));
				$logOptions = '	 | options: ( [title] => '.$options['title'].', [message] => '.$options['message'] . ' )';

			} elseif ($type == 'color') {
				$options['color'] = self::getExp($value, false);
				$logOptions = '	 | options: ( [color] => ' . $options['color'] . ' )';

			} elseif ($type == 'select') {
				$selectValue = self::getExp($value, false);
				$options['select'] = self::_findSelectValue($cmd_obj, $selectValue);
				if ($options['select'] === null) {
					self::message('', self::$__log_ERROR . __FUNCTION__ . " : Texte ou valeur introuvable dans la liste: $selectValue");
					return false;
				}
				$logOptions = '	 | options: ( [select] => ' . $options['select'] . ' )';

			} elseif ($type != 'other') {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : Sous-type de commande inconnu: " . $type);
				return false;
			}

			self::message('', self::$__log_SP . __FUNCTION__ . " : Commande de sous-type $type $logOptions");
			if (!self::_getCmdWait()) {
				$options['speedAndNoErrorReport'] = true;
			}
			try {
				$cmd_obj->execCmd($options);
			}
			catch (Exception $e) {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : ".$e->getMessage());
				return false;
			}
			catch (Error $e) {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : ".$e->getMessage());
				return false;
			}
			return true;

		} else {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver la commande $cmd");
			return false;
		}
	}

/************************************************************************************************************************
* Jeedom												GET WIDGET PARAM												*
*************************************************************************************************************************
* Renvoi les paramètres du widget associé à une commande.																*
*	Paramètres :																										*
*		Nom de la commande du widget																					*
*																														*
*		Retour : un tableau comportant tous les paramètres																*
************************************************************************************************************************/
	function getParamWidget($cmd, $complement='') {

		if ($complement) { $cmd = self::mkCmd($cmd, $complement); }

		$cmd = self::_tag($cmd);
		if (!$cmd) {
			return null;
		}
		$cmd_obj = self::_cmdbyString($cmd);
		if (!$cmd_obj) {
			$value = null;
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver la commande $cmd");
		}

		$param = $cmd_obj->getDisplay('parameters');
		self::message('', self::$__log_SP . __FUNCTION__ . " : Paramètres du widget de $cmd => " . print_r($param, true));
		return $param;
	}

/************************************************************************************************************************
* Jeedom												SET WIDGET PARAM												*
*************************************************************************************************************************
* Modifie les paramètres du widget associé à une commande.																*
*		(permet de suppléer à l'impossibilité de mettre des variables en paramètres au widget)							*
*																														*
*	Paramètres :																										*
*		Nom de la commande du widget																					*
*		Param : table des paramètres à poser																			*
*																														*
* Exemple pour ajouter/modifier le paramètre 'jauge'																	*
*		$tmp = mg::getParamWidget($inf, '');																			*
*		$tmp['jauge'] = 'vert';																							*
*		mg::setParamWidget($inf, '', $tmp);																				*
************************************************************************************************************************/
	function setParamWidget($cmd, $complement='', $param) {

		if ($complement) { $cmd = self::mkCmd($cmd, $complement); }

		$cmd = self::_tag($cmd);
		if (!$cmd) {
			return null;
		}
		$cmd_obj = self::_cmdbyString($cmd);
		if (!$cmd_obj) {
			$value = null;
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver la commande $cmd");
		}

		$cmd_obj->setDisplay('parameters', $param);
		$cmd_obj->save();
		self::message('', self::$__log_INF2 . __FUNCTION__ . " : Paramètres du widget de $cmd enregistrés");
	}

/************************************************************************************************************************
* Jeedom												GET TYPE EQUITY													*
*************************************************************************************************************************
* Renvoie le type de l'équipement																						*
* $eqLogic Id ou tag de l'équipement, avec ou sans les '*#*' autour														*
* Si vous utilisez l'id, vous pouvez l'entrer indifféremment avec ou sans les tag '*#*'									*
************************************************************************************************************************/
function getTypeEqui($eqLogic) {
	$eqLogic = self::_tag($eqLogic);
	if (!$eqLogic) {
	  return false;
	}
	$eqLogic_obj = eqLogic::byId(str_replace(array('#eqLogic', '#'), '', self::_humanReadableToEqLogic($eqLogic)));
		if (is_object($eqLogic_obj)) {
			$eqLogic = (self::_isEqLogicId($eqLogic)) ? self::_tag($eqLogic_obj->getHumanName()) : $eqLogic;
			$type = $eqLogic_obj->getEqType_name();
			self::message('', self::$__log_SP . __FUNCTION__ . " : Le type de l'équipement $eqLogic est $type.");
			return $type;
		}
	self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver l'équipement $eqLogic");
	return false;
  }

/************************************************************************************************************************
* Jeedom												IS ACTIVE														*
*************************************************************************************************************************
* Récupère l'état (actif ou inactif) de l'équipement (eqLogic)															*
* $eqLogic Id ou tag de l'équipement, avec ou sans les '*#*' autour														*
* Si vous utilisez l'id, vous pouvez l'entrer indifféremment avec ou sans les tag '*#*'									*
************************************************************************************************************************/
function isActive($eqLogic) {
	$eqLogic = self::_tag($eqLogic);
	if (!$eqLogic) {
	  return false;
	}
	$eqLogic_obj = eqLogic::byId(str_replace(array('#eqLogic', '#'), '', self::_humanReadableToEqLogic($eqLogic)));
		if (is_object($eqLogic_obj)) {
			$eqLogic = (self::_isEqLogicId($eqLogic)) ? self::_tag($eqLogic_obj->getHumanName()) : $eqLogic;
			$isActive = $eqLogic_obj->getIsEnable();
			$isActive = (is_numeric($isActive) && $isActive > 0) ? true : false;
			self::message('', self::$__log_SP . __FUNCTION__ . " : L'équipement $eqLogic est actif");
			return $isActive;
		}
	self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver l'équipement $eqLogic");
	return false;
  }

/************************************************************************************************************************
* Jeedom												IS VISIBLE														*
*************************************************************************************************************************
* Récupère l'état de visibilité de la commande ou de l'équipement (eqLogic)												*
* $cmd_or_eqLogic Id ou tag de la commande ou de l'équipement, avec ou sans les '*#*' autour							*
* Si vous utilisez l'id, vous pouvez l'entrer indifféremment avec ou sans les tag '*#*'									*
************************************************************************************************************************/
  function isVisible($cmd_or_eqLogic) {
	$tag = self::_tag($cmd_or_eqLogic);
	if (!$tag) {
	  return false;
	}
	$cmd_obj = self::_cmdbyString($tag);
	if ($cmd_obj) {
	  $cmd = (self::_isId($cmd_or_eqLogic)) ? self::_tag($cmd_obj->getHumanName()) : $cmd_or_eqLogic;
	  $isVisible = $cmd_obj->getIsVisible();
	  $isVisible = (is_numeric($isVisible) && $isVisible > 0) ? true : false;
	  self::message('', self::$__log_SP . __FUNCTION__ . " : La commande $cmd) est visible");
	  return $isVisible;
	}
	$eqLogic_obj = eqLogic::byId(str_replace(array('#eqLogic', '#'), '', self::_humanReadableToEqLogic($tag)));
		if (is_object($eqLogic_obj)) {
			$eqLogic = (self::_isEqLogicId($cmd_or_eqLogic)) ? self::_tag($eqLogic_obj->getHumanName()) : $cmd_or_eqLogic;
			$isVisible = $eqLogic_obj->getIsVisible();
			$isVisible = (is_numeric($isVisible) && $isVisible > 0) ? true : false;
			self::message('', self::$__log_SP . __FUNCTION__ . " : L'équipement $eqLogic est visible");
			return $isVisible;
		}
   self::message('', self::$__log_ERROR . __FUNCTION__ . " : Impossible de trouver la commande ou l'équipement $cmd_or_eqLogic");
	return false;
  }

/************************************************************************************************************************
* Jeedom												TO HUMAN														*
*************************************************************************************************************************
* Remplace tous les *#id#* dans une chaine par le nom (*#tag#*) des commandes et / ou des équipements					*
* Jeedom convertissant automatiquement tous les tags *#name#* vers *#id#* lors de la sauvegarde des blocs dans la BDD,	*
* cette fonction permet de récupérer le nom 'humain' (*#tag#*) des commandes et des équipements							*
* Votre chaine de texte pouvant contenir des *#name#* et/ou des *#id#* de commandes ou d'équipements					*
************************************************************************************************************************/
	function toHuman($texte, $complement='') {
		if ($complement) {
			$texte = self::MkCmd($texte, $complement);
		}

//		$texte = trim(trim($texte), '#');
		if (!$texte) {
			self::message('', self::$__log_WARNING . __FUNCTION__ . " : Le texte '$texte' passé en paramètre est vide !");
			return "";
		}
		//self::message('', self::$__log_SP . __FUNCTION__ . " : Les occurences entouré de '#' ont été remplacées par leur noms.");
		return self::_expressionToHumanReadable($texte);
	}

/************************************************************************************************************************
* Jeedom													TO ID														*
*************************************************************************************************************************
* Remplace tous les *#tag#* dans une chaine par l'id (*#id#*) des commandes et / ou des équipements						*
* Inverse de la fonction toHuman(), cette fonction permet de convertir les noms 'humains (*#tag#*) des commandes et		*
* des équipements vers les *#id#* correspondants																		*
* $texte Votre chaine de texte peut contenir des *#name#* et/ou des *#id#* de commandes ou d'équipements				*
************************************************************************************************************************/
	function toID($texte, $complement='') {
		if ($complement) {
			$texte = self::MkCmd($texte, $complement);
		}

		$texte = trim($texte, '#');
		$nb = substr_count($texte, '#');

		if (!$texte) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : L'equiLogic '$texte' passé en paramètre est vide !");
			return "";
		}

		if ($nb == 0) {
			$return = self::_expressionToId('#'.$texte.'#');
			self::message('', self::$__log_SP . __FUNCTION__ . " : L'equilogic de $texte est $return.");
			return $return;
		} else {
		//self::message('', self::$__log_SP . __FUNCTION__ . " : Les occurences entouré de '#' ont été remplacées par leur ID.");
			return self::_expressionToId($texte);
		}
	}

/************************************************************************************************************************
* Jeedom													FIND CMDS													*
*************************************************************************************************************************
* Trouve des commandes à l'aide de différents filtres																	*
* |%#e01005% Note: %| *Cette fonction ignore les équipements désactivés.												*
* Paramètres optionels :																								*
*	1) $objectFilter : Filtrage par nom d'objet (format regexp non sensible à la casse)									*
*		Ex: 'salon|cuisine' => tous les objets dont le nom contient *salon* ou *cuisine*								*
*	2) $category : Filtrage par catégorie																				*
*		Ex: 'light' (Catégories disponibles: 'heating', 'security', 'energy', 'light', 'opening', 'automatism', 'multimedia', 'default)
*	3) $eqFilter : Filtrage par nom d'équipement (format regexp non sensible à la casse)								*
*		Ex:* 'détecteur' => tous les équipements dont le nom contient *détecteur										*
*	4) $type : Filtrage par **type** de commande (*action* ou *info*)													*
*	5) $subTypeFilter : Filtrage par sous-type de commande *(format regexp non sensible à la casse)						*
*		Sous-types disponibles pour les commandes de type **info**: ('numeric', 'binary', 'string')						*
*		Sous-types disponibles pour les commandes de type action: ('other', 'slider', 'message', 'color', 'select')		*
*		Ex: 'numeric|binary' => tous les sous-type *numériques* et *binaires*											*
*	6) $cmdNameFilter : Filtrage par nom de la commande (format regexp non sensible à la casse)							*
*		Ex: 'on' => toutes les commandes dont le nom contient *on* (On, On 1, On 2...)									*
*	7) $genericTypeFilter : Filtrage par type générique de la commande (format regexp non sensible à la casse)			*
*		Types génériques disponibles: 'LIGHT_ON', 'LIGHT_OFF', 'LIGHT_STATE', 'ENERGY_ON', 'ENERGY_OFF', ...			*
*																														*
* Retourne la liste des commandes au format *#tag#* sous forme de tableau												*
*																														*
* Exemple complet :																										*
* Trouve toutes les commandes OFF des lumières du salon et de la cuisine												*
*	// On se base sur le nom d'objet, la catégorie d'équipement et le nom de la commande								*
*	// mg::findCmds(/Nom objet/i, Catégorie, /Nom équipement/i, Type, /Sous-type/i, /Nom commande/i, /Type générique/i)	*
*	$cmds = mg::findCmds('salon|cuisine', 'light', '', 'action', 'other', 'off');										*
*	// On lance toutes les commandes OFF des lampes trouvées :															*
*	foreach ($cmds as $cmd) {																							*
*		mg::setCmd($cmd);																								*
*	}																													*
************************************************************************************************************************/
	function findCmds($objectFilter = '', $category = '', $eqFilter = '', $type = '', $subTypeFilter = '', $cmdNameFilter = '', $genericTypeFilter = '') {
		if ($category) {
			$category = strtolower(trim($category));
			$catAvailable = array('heating', 'security', 'energy', 'light', 'opening', 'automatism', 'multimedia', 'default');

			if (!in_array($category, $catAvailable)) {
				self::message('', self::$__log_SP . __FUNCTION__ . " : La catégorie n'est pas valide (catégorie valides : ". implode(", ", $catAvailable) . ")");
				return self::_returnCmds(array());
			}
		}
		if (!$type) {
			$type = null;
		}
		else {
			$type = strtolower(trim($type));
			if ($type != 'info' && $type != 'action') {
				self::message('', self::$__log_SP . __FUNCTION__ . " : Si le type est spécifié, sa valeur doit être 'info' ou 'action'");
				return self::_returnCmds(array());
			}
		}
		return self::_getCmds($objectFilter, $category, $eqFilter, $type, $subTypeFilter, $cmdNameFilter, $genericTypeFilter);
	}

		// Fonction _returnCmds
		function _returnCmds($returnList) {
			$count = count($returnList);
			$pluriel = ($count > 1) ? 's' : '';
			self::message('', self::$__log_SP . __FUNCTION__ . " : ". (($count > 0) ? '' : 'ERREUR ') . $count . ' commande' . $pluriel . ' trouvée' . $pluriel . "<br>".print_r($returnList, true));
			return $returnList;
		}

		// Fonction _cmdsFilter
		function _cmdsFilter($returnList, $cmds, $cmdNameFilter, $subTypeFilter, $genericTypeFilter, $eqLogic = null) {
			foreach ($cmds as $cmd) {
				if (is_object($cmd)) {
					if ($eqLogic == null) {
						$eqLogic = $cmd->getEqLogic();
					}
					if ( is_object($eqLogic) && $eqLogic->getIsEnable() == 1
						&& (!$cmdNameFilter || preg_match('/' . $cmdNameFilter . '/i', $cmd->getName()))
						&& (!$subTypeFilter || preg_match('/' . $subTypeFilter . '/i', $cmd->getSubtype()))
						&& (!$genericTypeFilter || preg_match('/' . $genericTypeFilter . '/i', $cmd->getGeneric_type()))
					) {
						$returnList[] = self::_tag($cmd->getHumanName());
					}
				}
			}
			return $returnList;
		}

		// Fonction _getCmds
		function _getCmds($objectFilter, $category, $eqFilter, $type, $subTypeFilter, $cmdNameFilter, $genericTypeFilter) {
			$params = array();
			if ($objectFilter) $params[] = 'Objet => ' . $objectFilter;
			if ($category) $params[] = 'Catégorie => ' . $category;
			if ($eqFilter) $params[] = 'Equipement => ' . $eqFilter;
			if ($type) $params[] = 'Type => ' . $type;
			if ($subTypeFilter) $params[] = 'Sous-type => ' . $subTypeFilter;
			if ($cmdNameFilter) $params[] = 'Nom commande => ' . $cmdNameFilter;
			if ($genericTypeFilter) $params[] = 'Type générique => ' . $genericTypeFilter;
			$params = (count($params) > 0) ? '(' . implode(', ', $params) . ')' : '(toutes !)';
			self::message('', self::$__log_SP . __FUNCTION__ . " : Recherche les commandes $params");
			if ($type && !$objectFilter && !$eqFilter  && !$category) {
				$returnList = self::_cmdsFilter($returnList, cmd::byTypeSubType($type, ''), $cmdNameFilter, $subTypeFilter, $genericTypeFilter);
			}
			else {
				foreach (($category) ? eqLogic::byCategorie($category) : eqLogic::all() as $eqLogic) {
					if ( is_object($eqLogic)
						&& (!$objectFilter || (is_object($eqLogic->getObject()) && preg_match('/' . $objectFilter . '/i', $eqLogic->getObject()->getName())))
						&& (!$eqFilter || preg_match('/' . $eqFilter . '/i', $eqLogic->getName()))
					) {
					$returnList = self::_cmdsFilter($returnList, $eqLogic->getCmdByGenericType($type), $cmdNameFilter, $subTypeFilter, $genericTypeFilter, $eqLogic);
					}
				}
			}
			return self::_returnCmds($returnList);
		}
//	}

/************************************************************************************************************************
* Jeedom												EXTRACT_PART_CMD												*
*************************************************************************************************************************
* Extraction d'un segment d'un équipement, si pas une commande ou un équipement renvoie trim($cmd, '#')					*
*	Paramètres :																										*
*		$cmd : Nom de la commande ou de l'équipement.																	*
*		$position : Position à extraire (max 3 pour les commandes, 2 pour les équipements)								*
************************************************************************************************************************/
	function extractPartCmd($cmd, $position = 3) {
		$cmd = trim(self::toHuman($cmd), '#');
		if (strpos($cmd, ']') !== false) {
			$detailCmd = explode('[', $cmd);
			if ($position == 0 || count($detailCmd) < ($position+1)) {
				self::message('', self::$__log_ERROR . __FUNCTION__ . " : La position '$position' n'existe pas !!!");
				return;
			}
			$cmd = trim($detailCmd[$position], ']');
		}
		return $cmd;
	}

/************************************************************************************************************************
*																														*
*												FONCTIONS SUR LES VARIABLES												*
*																														*
************************************************************************************************************************/
function FONCTIONS_VARIABLES(){}

/************************************************************************************************************************
* Jeedom													GET VAR														*
*************************************************************************************************************************
* Lit une variable JEEDOM																								*
************************************************************************************************************************/
	function getVar($varName, $default = null) {
		global $scenario;
		$varName = trim($varName);
		if (!$varName) {

			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le nom de la variable ne peut pas être vide");
			return;
		}
		$value = $scenario->getData($varName);

		if ($value == null) {
			$value = $default;
			if (gettype($value) == 'array') { $valueAff = ''; }
			elseif (abs($value) > 1000000000) {
					$valueAff = "$value (" . date('d\/m\/Y \à H\hi\m\n', abs($value)) . ")";
				} else {
					$valueAff = $value;
				}
			self::message('', self::$__log_SP . __FUNCTION__ . " : Variable $varName == $valueAff **par defaut** ");

		}
		else {
			if (gettype($value) == 'array') {
				$valueAff = 'Array';
			} else if (abs($value) > 1000000000) {
				$valueAff = "$value (" . date('d\/m\/Y \à H\hi\m\n', abs($value)) . ")";
			} else {
				$valueAff = self::shorterMessage($value);
			}
			self::message('', self::$__log_SP . __FUNCTION__ . " : Variable $varName == " . $valueAff);
		}
		return $value;
	}

/************************************************************************************************************************
* Jeedom													SET VAR														*
*************************************************************************************************************************
* Ecrit une variable Jeedom en BdD																						*
* en sql : 'INSERT INTO `dataStore`	 (`type`, `link_id`, `key`, `value`) VALUES ('scenario', -1, 'VarName', 'valTest');'"
************************************************************************************************************************/
	function setVar($varName, $value = "") {
		$varName = trim($varName);

		if (!$varName) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le nom de la variable ne peut pas être vide");
			return;
		}

		global $scenario;

		if (gettype($value) == 'array') { $valueAff = ''; }
		elseif (is_int($value) ? abs($value) > 1000000000 : false) {
				$valueAff = "$value (" . date('d\/m\/Y \à H\hi\m\n', abs($value)) . ")";
		} else {
			$valueAff = $value;
//			if ((is_string($value)) ? strlen($value) > 100 : false) { $valueAff = substr($value,0 , 100) . ' (...)'; }
		}

		$scenario->setData($varName, $value);
		self::message('', self::$__log_INF2 . __FUNCTION__ . " : Variable $varName ==> " . $valueAff);
	}

/************************************************************************************************************************
* Jeedom												UNSET VAR														*
*************************************************************************************************************************
* Détruit une variable JEEDOM																							*
************************************************************************************************************************/
	function unsetVar($varName) {
		global $scenario;
		$varName = trim($varName);
		$unset = false;
		if (!$varName) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le nom de la variable ne peut pas être vide");
			return;
		}
		$No_Exist = 'VaR¨=¨ImPoSiBle_To-HavE^$This.[vAlue#In~@£VarIaBlE;,. Is°iT"{SuR]??__ I&SaY§...\"YeS|-\\\\ oF²cOurs€^^ !!!\"';
		if ($scenario->getData($varName, false, $No_Exist) !== $No_Exist) {
			$scenario->removeData($varName);
			self::message('', self::$__log_SP . __FUNCTION__ . " : Destruction de la variable $varName");
			$unset = true;
		}
		if (!$unset) {
			self::message('', self::$__log_SP . __FUNCTION__ . " : Variable $varName non définie, aucune destruction");
		}
	}

/************************************************************************************************************************
* UTIL													AFF VAR															*
*************************************************************************************************************************
* Génère et Affiche dans le log et retourne un message avec le nom et la valeur de chaque variables en BdD demandées.	*
*	Paramètres :																										*
*		$tabVar		Nom des variables demandées (séparé par une virgule)												*
*		exemple		AffVar('NuitSalon, NuitExt, _designActif, _VoletGeneral');											*
************************************************************************************************************************/
	function affVar($tabVar) {
		 $message = '';
		 $listVar = explode(',', $tabVar);
		for ($i = 0; $i < count($listVar); $i++) {
			$name = trim($listVar[$i]);
			$value = trim($listVar[$i]);

			if ($name != '') {
				 $value = self::getVar($name);
				if ( $value > 1000000000) {
						$value = date('d\/m\/Y \à H\hi\m\n', $value);
				} elseif ( $value == null ) {
						$value = '*** Inconnue ***';
						}
				$message .= "$name = $value -- ";
			}
		}
		$message = trim($message, ' -- ');
		self::message('', self::$__log_SP . __FUNCTION__ . " : $message");
		return $message;

	}

/************************************************************************************************************************
*																														*
*												FONCTIONS CONDITIONNELLES												*
*																														*
************************************************************************************************************************/
function FONCTIONS_CONDITIONNELLES(){}

/************************************************************************************************************************
* Jeedom												GET EXP															*
*************************************************************************************************************************
* Récupère la valeur d'une expression avec le moteur d'expression de jeedom												*
* Supporte tous les tags jeedom ainsi que les fonctions Jeedom et les fonctions php										*
************************************************************************************************************************/
	function getExp($exp) {
		global $scenario;
		$exp = trim($exp);
		if ($exp === null || $exp === "") {
			self::message('', self::$__log_WARNING . __FUNCTION__ . " : Évaluation d'une expression vide (retourne null)");
			return null;
		}
		$return = evaluate(scenarioExpression::setTags(self::_expressionToId($exp), $scenario));

		if ($return === $exp) {
			self::message('', self::$__log_WARNING . __FUNCTION__ . " : L'évaluation de l'expression est égale à l'expression ( '$exp' => '$return' )");
		} else {
			//self::message('', self::$__log_SP . __FUNCTION__ . " : Résultat => '$return'");
		}
		return $return;
	}

/************************************************************************************************************************
* Jeedom											GET CONDITION														*
*************************************************************************************************************************
* Evalue une condition jeedom																							*
*	Supporte tous les tags jeedom ainsi que les fonctions Jeedom et les fonctions php									*
*	La liste des tags et des fonctions disponibles se trouve sur la documentation officielle :							*
*	https://www.jeedom.com/doc/documentation/core/fr_FR/doc-core-Scenario.html											*
************************************************************************************************************************/
	function getCond($exp) {
		global $scenario;
		$exp = trim($exp);
		if ($exp === null || $exp === "") {
			self::message('', self::$__log_WARNING . __FUNCTION__ . " : Évaluation d'une expression vide (retourne null)");
			return false;
		}
//		self::message('', self::$__log_SP . __FUNCTION__ . " : Évaluation de la condition " . self::_expressionToId($exp));
		$exp = scenarioExpression::setTags(self::_expressionToId($exp), $scenario, true);
		self::message('', self::$__log_SP . __FUNCTION__ . " : Condition évaluée a " . $exp);
			$return = evaluate($exp);
			if ($return === 0) {
				$return = false;
			}
			elseif ($return === 1) {
				$return = true;
			}
		if (is_bool($return)) {
			self::message('', self::$__log_SP . __FUNCTION__ . " : Condition évaluée a " . (int)$return);
		}
/*		else {
			$return = false;
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : La syntaxe de la condition n'est pas valide (retourne null)");
		}*/
		return $return;
	}

/************************************************************************************************************************
* Jeedom													WAIT														*
*************************************************************************************************************************
* Attente de la validation d'une condition																				*
*	Paramètres :																										*
*		- La condition, doit être directement interprétable dans jeedom (tester avec le testeur d'expression)			*
*		- Le TimeOut																									*
*	Exemple de conditions acceptées :																					*
*		wait("Scenario($ScenarioID) == 0", 180);																		*
*		wait("variable(NuitSalon) == 1", 10);																			*
*		wait("Salon][Oeil bureau][Présence]# == 0", 10)																	*
************************************************************************************************************************/
	function wait($condition, $timeout = 7200) {
		global $scenario;
		$timeout = min(max($timeout, 1), 7200);
		$condition = trim($condition);
		if (!$condition) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : L'expression est vide, le temps dattente sera le temps d'attente maxixum (7200 sec)");
		}
		else {
			self::message('', self::$__log_SP . __FUNCTION__ . " : Attendre jusqu'à ce que " . self::_expressionToHumanReadable($condition));
		}
		$return = self::__action('wait', array("condition" => self::_expressionToId($condition), "timeout" => $timeout));
	}

/************************************************************************************************************************
*																														*
*												FONCTIONS DIVERSES														*
*																														*
************************************************************************************************************************/
function FONCTIONS_DIVERSES(){}

/************************************************************************************************************************
*													ZWAVE_ACTION														*
*************************************************************************************************************************
* Lance une action sur un noeud et attend la fin pour sortir															*
*																														*
*	Paramètres																											*
*	action		: Action Zwave à effectuer :																			*
*							'testNode'						Exécuter un test (ping)										*
							'refreshAllValues'				Demande de rafraîchir toutes les valeurs du nœud			*
							'requestNodeNeighbourUpdate'	Demande de mise à jour des voisins du noeud					*
							'healNode'						Demande à soigner le noeud									*
							'hasNodeFailed'					Demande si le nœud est présumé mort par le contrôleur		*
							'requestNodeDynamic'			Demande de refaire l’étape de l’interview Dynamic			*
							'assignReturnRoute'				Forcer une route de retour au contrôleur					*
*																														*
************************************************************************************************************************/
function ZwaveAction($nodeId, $action) {
	global $scenario;
	$apizwave = self::getConfigJeedom('openzwave');
	self::ZwaveBusy(); // Attente de dispo de Zwave
	$url = "http://127.0.0.1:8083/node?node_id=$nodeId&type=action&action=$action&apikey=$apizwave";
	mg::messageT('', ". Action $action noeud $nodeId");

	self::ZwaveBusy(); // Attente de dispo de Zwave
	$contents = file_get_contents($url);
	$results = json_decode($contents);
	$success = $results->state;
	self::message('', "Action $action noeud $nodeId return $success");
}

/************************************************************************************************************************
*													SOIGNE ZWAVE														*
*************************************************************************************************************************
* Mise à jour des nœuds voisins																							*
* Une autre fonctionnalité qui a été désactivée dans Jeedom et toujours pour des raisons de trafic réseau inutile,		*
* spécialement si votre réseau est stabilisé ou que vous ne déplacez jamais vos modules.								*
																														*
* Dans l’éventualité ou vous apportez des changements dans la topologie de votre réseau, il sera alors intéressant		*
* durant quelques semaines de forcer des mises à jour des routes afin d’affiner au mieux la qualité de votre maillage et*
* calcul des sauts afin de rejoindre le contrôleur.																		*
************************************************************************************************************************/
function zwaveSoins() {
	global $scenario;
	$apizwave = self::getConfigJeedom('openzwave');
	// call the network health endpoint
	$url_health = 'http://localhost:8083/controller?type=action&action=healNetwork&apikey=' . $apizwave;
	$content = file_get_contents($url_health);
	mg::ZwaveBusy(5);
	mg::message($logTimeLine, $content);
	// get result as json
	$results = json_decode($content, true);
	$success = $results["state"];
	if ($success != 'ok') {
		$scenario->setLog('ZAPI controller healNetwork return une erreur: ' . $results["result"]);
	}
}

/************************************************************************************************************************
*														ZWAVE BUSY														*
*************************************************************************************************************************
* Attend que le server ne soit plus 'busy' SI $timer > 0 via un ping sur le Node N° 1,									*
* Si timer = 0 renvoie directement la valeur de la queue Zwave															*
************************************************************************************************************************/
function ZwaveBusy($timer = 0, $queueMax=0, $echo = '') {
	$networkState = openzwave::callOpenzwave('/network?type=info&info=getStatus');
	$queueSize = $networkState['result']['outgoingSendQueue'];

	$oldQueueSize = 0;
	while ($timer > 0 && $queueSize > $queueMax) {
		$networkState = openzwave::callOpenzwave('/network?type=info&info=getStatus');
		$queueSize = $networkState['result']['outgoingSendQueue'];
		if ($oldQueueSize != $queueSize && $echo) { mg::message('', "Attente Queue sortante à 0 : $queueSize"); }
		$oldQueueSize = $queueSize;
		sleep(intval($timer));
	}
	//	mg::message('', print_r($networkState, true));
	return intval($queueSize);
}

/************************************************************************************************************************
* Jeedom												GET TAG															*
*************************************************************************************************************************
* Récupère la valeur d'un tag avec le moteur d'expression de jeedom														*
* $tag Id ou nom de tag, avec ou sans les '*#*' autour																	*
************************************************************************************************************************/
	function getTag($tag = '') {
		  global $scenario;
		$tag = self::_tag($tag);
		if (!$tag) {
		  return null;
		}
		if ($tag == "#trigger#") {
		  $return = self::__trigger();
		}
		else {
		  $tag = self::_humanReadableToCmd($tag);
		  $return = trim(trim(scenarioExpression::setTags($tag, $scenario), '"'), "'");
		}
	if ($return == $tag) {
	  self::message('', self::$__log_SP . __FUNCTION__ . " : L'évaluation du tag a échoué ( " . self::_cmdToHumanReadable($tag) . " => '$return' )");
	}
	else {
		self::message('', self::$__log_SP . __FUNCTION__ . " : " . self::_cmdToHumanReadable($tag) . " == '$return'");
	}
		return $return;
	}

/************************************************************************************************************************
* Jeedom											SET MESSAGE JEEDOM													*
*************************************************************************************************************************
	Ajoute un message dans le centre de message Jeedom																	*
************************************************************************************************************************/
	function setmessageJeedom($message) {
		$message = trim($message);
		if (!$message) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le message ne peut pas être vide");
			return;
		}

		self::message('', self::$__log_SP . __FUNCTION__ . " : Message Jeedom ==> $message");
		message::add('scenario (classe ' . get_class() .')', $message);
		return true;
	}

/************************************************************************************************************************
* Jeedom											SET ALERTE JEEDOM													*
*************************************************************************************************************************
* Permet d’afficher un message d’alerte (avec 4 niveaux) sur tous les navigateurs										*
*	(Ne fonctionne que si un onglet Jeedom est ouvert dans le navigateur)												*
	********************************************************************************************************************/
	function alertJeedom($message, $level) {
		$message = trim($message);
		if (!$message) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le message ne peut pas être vide");
			return;
		}
		$level = strtolower(trim($level));
		$levels = array('success', 'warning', 'danger', 'info');
		if (!in_array($level, $levels)) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le niveau d’alerte n'est pas valide (niveaux valides: ". implode(", ", $levels) . ")");
			return;
		}
		event::add('jeedom::alert', array("message" => $message, "level" => $level));
	}

/************************************************************************************************************************
* Jeedom											SET POPUP JEEDOM													*
*************************************************************************************************************************
* Permet d’afficher un popup (qui doit absolument être validé) sur tous les navigateurs.								*
*	Ne fonctionne que si un onglet Jeedom est ouvert dans le navigateur.												*
************************************************************************************************************************/
	function popupJeedom($message) {
		$message = trim($message);
		if (!$message) {
		  self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le message ne peut pas être vide");
		  return;
		}
		event::add('jeedom::alertPopup', $message);
	}

/************************************************************************************************************************
* Jeedom												SAY																*
*************************************************************************************************************************
* Permet de faire dire un texte à Jeedom									.											*
*	(Ne marche que si un onglet Jeedom est ouvert dans le navigateur).													*
************************************************************************************************************************/
	function say($message) {
		$message = trim($message);
		if (!$message) {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : Le message ne peut pas être vide");
			return false;
		}
		self::message('', self::$__log_ERROR . __FUNCTION__ . " : Action Obsolète !!!");
		event::add('jeedom::say', $message);
		return true;
	}

/************************************************************************************************************************
* Jeedom												GO TO DESIGN													*
*************************************************************************************************************************
* Change le design affiché (sur tous les navigateurs actifs) par le design demandé
*	@param int $design_id L'id du design à afficher
************************************************************************************************************************/
	function goToDesign($design_id) {
		$design_id = intval($design_id);
		if (is_int($design_id) && $design_id > 0) {
			self::message('', self::$__log_SP . __FUNCTION__ . " : Changement design vers le design ayant l'id $design_id");
			event::add('jeedom::gotoplan', $design_id);
		}
		else {
			self::message('', self::$__log_ERROR . __FUNCTION__ . " : L'id du design ($design_id) à atteindre n'est pas un nombre valide");
		}
	}

/************************************************************************************************************************
* Jeedom											JEEDOM POWEROFF														*
*************************************************************************************************************************
* Demande à Jeedom de s’éteindre
************************************************************************************************************************/
	function jeedom_poweroff() {
		self::message('', self::$__log_INF2 . __FUNCTION__ . " : Demande d'extinction de Jeedom");
		self::__action('jeedom_poweroff');
	}

/************************************************************************************************************************
* Jeedom											JEEDOM REBOOT														*
*************************************************************************************************************************
* Demande à Jeedom de redémarrer
************************************************************************************************************************/
	function jeedom_reboot() {
		self::message('', self::$__log_INF2 . __FUNCTION__ . " : Demande de redémarrage de Jeedom");
		self__action('jeedom_reboot');
	}

/************************************************************************************************************************
*																														*
*													SOUS PROGRAMMES GENERIQUES											*
*																														*
*************************************************************************************************************************/
	private static function __trigger() {
		global $scenario;
		$trigger = $scenario->getRealTrigger();
		$cmd_obj = cmd::byId(str_replace('#', '', $trigger));
		return (is_object($cmd_obj)) ? $cmd_obj->getHumanName() : $trigger;
	}

	private static function __action($action, $_options = null, $sendScenario = true) {
		global $scenario;
		$scenarioExpression = new scenarioExpression();
			$scenarioExpression->setType('action');
			$scenarioExpression->setExpression($action);
			if (is_array($_options)) {
				foreach ($_options as $key => $value) {
					$scenarioExpression->setOptions($key, $value);
				}
			}
			return ($sendScenario) ? $scenarioExpression->execute($scenario) : $scenarioExpression->execute();
	}

	  private static function __getStopException() {
		return self::$__stop_exception;
	  }

	//TAG
	protected function _tag($tag) {
		$tag = trim(trim($tag), '#');
		if (!$tag) {
			self::message('', "Le tag ne peut pas être vide");
			return null;
		}
		return ("#".$tag.'#');
	}

	//CMD
	protected function _getCmdWait() {
		return self::$__wait_cmd;
	}

	protected function _setCmdWait($wait) {
		self::$__wait_cmd = $wait;
	}

	protected static function _isId($cmd) {
		return preg_match("/^#\d+#$/", $cmd);
	}

	protected static function _isEqLogicId($eqLogic) {
		return preg_match("/^#(eqLogic)?\d+#$/", $eqLogic);
	}

	protected static function _isTag($cmd) {
		return preg_match("/^#.+#$/", $cmd);
	}

	protected static function _humanReadableToCmd($cmd) {
		return (!self::_isId($cmd)) ? cmd::humanReadableToCmd($cmd) : $cmd;
	}

	protected static function _cmdToHumanReadable($cmd) {
		return (self::_isId($cmd)) ? cmd::cmdToHumanReadable($cmd) : $cmd;
	}

	protected static function _humanReadableToEqLogic($eqLogic) {
		return (!self::_isEqLogicId($eqLogic)) ? eqLogic::fromHumanReadable($eqLogic) : $eqLogic;
	}

	protected static function _eqLogicToHumanReadable($eqLogic) {
		return (self::_isEqLogicId($eqLogic)) ? eqLogic::toHumanReadable($eqLogic) : $eqLogic;
	}

	protected static function _cmdbyString($cmd) {
		$cmd_obj = cmd::byId(str_replace('#', '', self::_humanReadableToCmd($cmd)));
		return (is_object($cmd_obj)) ? $cmd_obj : null;
	}

	protected static function _expressionToHumanReadable($exp) {
		$exp = preg_replace_callback("/#\d+#/", function($cmd) {
	  return self::_cmdToHumanReadable($cmd[0]);
		}, $exp);
		$exp = preg_replace_callback("/#(eqLogic)?\d+#/", function($eqLogic) {
	  return self::_eqLogicToHumanReadable($eqLogic[0]);
		}, $exp);
		return $exp;
	}

	protected static function _expressionToId($exp) {
		$exp = preg_replace_callback("/#.+#/", function($cmd) {
		return self::_humanReadableToCmd($cmd[0]);
		}, $exp);
		$exp = preg_replace_callback("/#.+#/", function($eqLogic) {
	  return self::_humanReadableToEqLogic($eqLogic[0]);
		}, $exp);
		$exp = preg_replace_callback("/trigger\((.*?)\)/", function($cmd) {
		global $scenario;
		return '"' . $scenario->getRealTrigger() . '"';
		}, $exp);
		return str_replace("#trigger#", '"' . self::__trigger() . '"', $exp);
	}

	  protected static function _findSelectValue($cmd_obj, $selectValue) {
		$cmdConfig = $cmd_obj->getConfiguration();
		if (is_array($cmdConfig) && $cmdConfig['listValue']) {
		  foreach (explode(';', $cmdConfig['listValue']) as $list) {
			$selectData = explode('|', $list);
			if ($selectData && (
			  strtolower($selectData[0]) == strtolower($selectValue) ||
			  (count($selectData) > 1 && strtolower($selectData[1]) == strtolower($selectValue))
			)) {
			  return $selectData[0];
			}
		  }
		}
		return null;
	  }

	// FIN Fonctions de travail

/************************************************************************************************************************
*																														*
*													Fin Classe MG														*
*																														*
************************************************************************************************************************/
}

?>