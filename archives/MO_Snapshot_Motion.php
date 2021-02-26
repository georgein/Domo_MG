<?php

/******************************************************************************************************************************
Snapshot Motion - 100
Gère les détecteurs virtuels de mouvement, déclenché par incron, avec les infos des caméras.
Incron positionne le flag MotionCaméra et déplace les fichiers vers le répertoire de destination.

Les détecteurs sont inhibés si Alarme inactive et NuitExt == 0 et NuitSalon == 0 et $GenreCaméra = Salon pour ne pas les utiliser en journée.
La mémorisation des fichiers pour le widget n'a lieu qu'en Alarme OU si NuitSalon == 2.
Pour nettoyer les fichiers avant les changements de mode, le scénario est lancé par l'alarme et NuitSalon.

La purge supprime tous les fichiers .jpg du répertoire destination de plus de $SnapDureePurge heures.
La surveillance des rep de dépot des jpg est relégué à incron, les fichiers nécessaire sont généré automatiquement (code en bas de page)
-----------------------------------------------------------------------------------------------------------------------------
Install de incron ( https://doc.ubuntu-fr.org/incron )
	sudo apt-get install incron					==> Install
	sudo rm -f	/etc/incron.allow				==> Suppression du fichier de droit de incron
	echo $USER | sudo tee -a /etc/incron.allow	==> Ajout des droits pour le user courant
	sudo chmod -R 777 /etc/incron.d				==> Ouverture de droit sur le répertoire pour incron.ref
-----------------------------------------------------------------------------------------------------------------------------
Convert - transpo des jpg voir :
http://debian-facile.org/doc:media:imagemagick

**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $equipMeteoFrance
// $PathDatasCamera

// N° des scénarios :

	$scenParametrages = 137;
	
//Variables :
	$RepUtilMg = getRootPath() . "/mg/util";
	$PathIncron = '/etc/incron.d';
	$Alarme = mg::getVar('Alarme');
	$NuitSalon = mg::getVar('NuitSalon');
	$NuitExt = mg::getVar('NuitExt');
	$VentFort = mg::getCmd($equipMeteoFrance, 'VentFort');

// Paramètres :
	$snapJourMax = mg::getParams('Snapshots', 'jourMax');		// Nombre de jours maximum de conservations des snapshots
	$snapVolMax = mg::getParams('Snapshots', 'volMax');			// Volume max en Mo de snapshots par répertoire de caméra
	$snapFormatJPG = mg::getParams('Snapshots', 'formatJPG');	// Format pour transpo des snaphot (cf imagemagick)
	$LogTimeLine = mg::getParams('Log', 'timeLine');

	$IP_Jeedom = mg::getTag('#IP#');
	$API_Virtual = mg::getAPI('virtual');
	$API_Jeedom = mg::getAPI('core');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$fileParamIni_sh = "$RepUtilMg/param_ini.sh";
$Incron= '';
$messageTimeLine = '';

foreach ($Tab_Cameras as $NomCamera => $DetailsCamera) {
	//=================================================================================================================
	mg::messageT('', "! ************************************* $NomCamera *******************************************");
	//=================================================================================================================
	$pathSource = trim($DetailsCamera[0]);
	$InfCam = trim($DetailsCamera[2], '#');
	$ID_Cam = intval($DetailsCamera[3]);
	$cdNuitSalon = trim($DetailsCamera[4]);
	$cdNuitExt = trim($DetailsCamera[5]);
	$cdVentFort = trim($DetailsCamera[6]);
	$viaFTP = intval($DetailsCamera[7]);
	$typeCam = trim($DetailsCamera[8]);
	$cdMemoNuitSalon = trim($DetailsCamera[9]);

	$pathCible = "$PathDatasCamera/$ID_Cam";
	$File_sh = "$RepUtilMg/$NomCamera.sh";

	$oldMemoCamera = mg::getVar("_Memo_$NomCamera");

	$InhibMotionCam = 0;
	$FileMotionCam = "";
	$memoCamera = 0;

	if ($viaFTP) {
		if (!file_exists($pathCible)) {
			shell_exec("sudo mkdir '$pathCible'");
		}
		shell_exec("sudo chmod -R 777 $pathCible");
	}

//=================================================================================================================
	// ============================================ GESTION FICHIERS INCRON ===========================================
	// ================================== INHIBITION MOTION CAMERA SELON PARAMETRAGES =================================
	//=================================================================================================================
	eval("\$cd1 = ($NuitSalon$cdNuitSalon);");
	eval("\$cd2 = $NuitExt$cdNuitExt;");
	eval("\$cd3 = $VentFort$cdVentFort;");
	if ($cd1 || $cd2 || $cd3) {
		$InhibMotionCam = 1;
	} else {
		// Préparation des fichiers pour incron	 si pas inhibé
		$FileMotionCam = "wget -O $RepUtilMg/Result 'http://$IP_Jeedom/core/api/jeeApi.php?apikey=$API_Virtual&type=virtual&id=$InfCam&value=1'";
		if ($viaFTP) {
			$Incron .= "$pathSource IN_CREATE $File_sh\n";
		}
	}

	// ====================================== GESTION MEMO DES SNAPSHOTS DES CAMERAS ==================================
	// En Alarme OU Selon condition donné par MemoNuitSalon
	eval("\$cdMemo = ($NuitSalon$cdMemoNuitSalon);");
	if ($Alarme || $cdMemo) {
		$memoCamera = 1;
		$FileMotionCam .= "\n sudo mv $pathSource/*.jpg $pathCible";
	}
	// ================================== ENREGISTREMENT FICHIER file.sh de $NomCamera ===============================
	file_put_contents($File_sh, $FileMotionCam);

	//================================================================================================================
	// ********************************** RENOMMAGE / CONVERSION DES SNAPSHOTS ***************************************
	//================================================================================================================
	// FOSCAM
	if ($typeCam == 'FOSCAM') {
		// Suppression des Snap Source et Cible MDAlarm_*.jpg si oldMémo ou Mémo == 0
		if (!$oldMemoCamera || !$memoCamera) {
			$res = shell_exec("ls $pathCible");
			if (strpos($res, 'MDAlarm_') !== false) {
				shell_exec("sudo rm $pathCible/MDAlarm_*.jpg");
			}
			$res = shell_exec("ls $pathSource");
			if (strpos($res, 'MDAlarm_') !== false) {
				shell_exec("sudo rm $pathSource/MDAlarm_*.jpg");
			}
		}

		if ($memoCamera) {
			foreach(glob("$pathCible/MDAlarm_*.jpg") as $file) {
				if (strpos($file, 'MDAlarm_') != false) {
					$date = str_replace('MDAlarm_', '', $file);
					$date = substr($date, -19);
					$date = $date[0].$date[1].$date[2].$date[3].'-'.$date[4].$date[5].'-'.$date[6].$date[7].'_'.$date[9].$date[10].'-'.$date[11].$date[12].'-'.$date[13].$date[14];
					$fileNew = $pathCible . "/" . $NomCamera . "_" . $date;
					shell_exec("sudo convert '$file' $snapFormatJPG '$fileNew.jpg'");
					mg::message('', "Conversion FOSCAM vers $fileNew.jpg");
					shell_exec("sudo rm $file");
				}
			}
		}
	}

	// NETATMO
	if ($memoCamera && $typeCam == 'NETATMO') {
		$cmdLastEvents = '[Extérieur][Cam Entrée][Derniers évènements]';
		$lastEvents = explode('src=\'', mg::getCmd($cmdLastEvents));

		foreach($lastEvents as $lastEvent) {
			$event = explode(' - ', $lastEvent);
			$fileOrg = explode('\'', $event[0]);
			if (file_exists('/var/www/html/' . $fileOrg[0])) {
				if (strpos($fileOrg[0], 'tooltip') === false) {
					$date = substr($fileOrg[1], -19);
					$date = str_replace(':', '-', $date);
					$date = str_replace(' ', '_', $date);
					$fileNew = $pathCible . "/" . $NomCamera . "_" . $date;
					if (file_exists("$fileNew.jpg") == false) {
						mg::message('', "Conversion NETATMO vers $fileNew.jpg");
						shell_exec("sudo convert '/var/www/html/$fileOrg[0]' " . trim($snapFormatJPG, '\\') . " '$fileNew.jpg'");
						mg::message('', "sudo convert '/var/www/html/$fileOrg[0]' $snapFormatJPG '$fileNew.jpg'");
					}
				}
			}
		}
	}

	// Transpo taille des captures du répertoire Cible si SI > 70 Mo
	foreach(glob("$pathCible/*.jpg") as $file) {
		$fileSource = basename($file);
		if (filesize($file) > 70000 ) {
			shell_exec("sudo convert '$file' $snapFormatJPG '$file'");
			mg::message('', "Transpo Taille de $file");
		}
	}

	// Nettoyage répertoire Cible par ancienneté et volume max
	mg::NettoieRep($pathCible."/*.jpg", $snapVolMax*1000000, $snapJourMax);

	mg::setVar("Motion_$NomCamera", $InhibMotionCam);
	mg::setVar("_Memo_$NomCamera", $memoCamera);
	$message = ("***$NomCamera*** Inhib. $InhibMotionCam - Mémo $memoCamera");
	$messageTimeLine .= "$message, ";
	mg::messageT('', " ******************************************* $message *******************************************");

	mg::setInf($InfCam, '', 0);
}

//====================================================================================================================
mg::messageT('', "! ******************************************* FIN	 **********************************************");
//====================================================================================================================
// Si modif du message reflétant le paramètrage on écris le fichier incron et on le relance
if ($messageTimeLine != mg::getVar('_Snapmessage')) {
	mg::setVar('_Snapmessage', $messageTimeLine);
//	mg::message($LogTimeLine, $messageTimeLine);

	// ================================================ PARAMETRAGES.INI ==============================================
	// Lancement du scénario Parametrages si MàJ de 'parametrages.ini' via incron
	$FileParamIni = "wget -O $RepUtilMg/Result 'http://$IP_Jeedom/core/api/jeeApi.php?apikey=$API_Jeedom&type=scenario&id=$scenParametrages&action=start'";
	file_put_contents($fileParamIni_sh, $FileParamIni);
	$Incron .= "/var/www/html/mg/PA_Parametrages.ini IN_MODIFY $fileParamIni_sh\n";
	// ================================================================================================================
	
	// Enregistrement fichier Incron
	file_put_contents("$PathIncron/incron.ref", $Incron);
	shell_exec('sudo service incron restart');
	mg::message('', "Redémarrage Incron");
}

?>