<?php

/******************************************************************************************************************************
Netatmo - 189
Gère les détecteurs virtuels de mouvement des caméras Netatmo
-----------------------------------------------------------------------------------------------------------------------------
Install de incron ( https://doc.ubuntu-fr.org/incron )
	sudo apt-get install incron					==> Install
	sudo rm -f	/etc/incron.allow				==> Suppression du fichier de droit de incron
	echo $user | sudo tee -a /etc/incron.allow	==> Ajout des droits pour le user courant
	sudo chmod -R 777 /etc/incron.d				==> Ouverture de droit sur le répertoire pour incron.ref
-----------------------------------------------------------------------------------------------------------------------------
Convert - transpo des jpg voir :
http://debian-facile.org/doc:media:imagemagick

**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	//$tab_Cameras
	
// N° des scénarios :

	$scenParametrages = 137;
	
//Variables :
	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$repUtilMg = getRootPath() . "$pathRef/util";
	$pathIncron = '/etc/incron.d';
	$fileParamIni_sh = "$repUtilMg/param_ini.sh";

// Paramètres :
	$IP_Jeedom = mg::getTag('#IP#');
	$API_Jeedom = mg::getConfigJeedom('core');

// ================================================ PARAMETRAGES.INI ==============================================
/*	
	$Incron = '';
	// Lancement du scénario Parametrages si MàJ de 'parametrages.ini' via incron
	$FileParamIni = "wget -O $repUtilMg/Result 'http://$IP_Jeedom/core/api/jeeApi.php?apikey=$API_Jeedom&type=scenario&id=$scenParametrages&action=start'";
	file_put_contents($fileParamIni_sh, $FileParamIni);
	$Incron .= "/var/www/html/mg /PA_Parametrages.ini IN_MODIFY $fileParamIni_sh\n";
	// ================================================================================================================
	
	// Enregistrement fichier Incron
	file_put_contents("$pathIncron/incron.ref", $Incron);
	shell_exec('sudo service incron restart');
	mg::message('', "Redémarrage Incron");
	*/

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$declencheur = mg::getTag('#trigger#');

$nomCamera = mg::ExtractPartCmd($declencheur, 2);
$detailsCamera = $tab_Cameras[$nomCamera];
$infMvmtCam = trim($detailsCamera[1], '#');
$mvmtCam = mg::getCmd($infMvmtCam);

//=================================================================================================================
mg::messageT('', "! $nomCamera - $nomCamera - $infMvmtCam - $mvmtCam");
//=================================================================================================================
if ($mvmtCam == 0) { 
	mg::setInf($infMvmtCam, '', 1);
} else {
	mg::setInf($infMvmtCam, '', 0);
}

?>