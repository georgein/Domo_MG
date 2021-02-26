<?php
/**********************************************************************************************************************
CO-COVID - 191

Gestion Edition/Envoi certificat Covid pendant les périodes d'entrainement.
	1 - Après avoir quitté Home (usage normal)
	2 - En dépassant la limite de 1 km, certificat antidaté à time()-9mn (permet de gagner au moins 10 mn sur la durée légale)
	3 - En repassant SOUS la limite de 1 km, certificat antidaté à time()-9mn (permet de reculer le retour 'in eternam', à conditions bien sur qu'il n'y est pas de contrôle entre les deux !!! :)

**********************************************************************************************************************/
deb:
// Infos, Commandes et Equipements :

// N° des scénarios :

// Variables :

// Paramètres :
	$tagUser = mg::getTag('user');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$Declencheur = mg::getTag('#trigger#');

//$user = (strpos($Declencheur, 'MG') !== false) ? 'MG' : (strpos($Declencheur, 'NR') !== false) ? 'NR' : '';
$user = mg::extractPartCmd($Declencheur, 3);
mg::message('', "===> $Declencheur - $user");

// Envoi demandé manuellement
if ($user == '' && strpos($tagUser, '#') === false) {
	$user = $tagUser;
	$timeEnvoi = time();
	goto envoi;
}

if ($user == '' || $user == 'user') {
	$user = 'MG';
	mg::messageT('', "Lancement manuel sur user 'MG'");
}
$userPresent = mg::getCmd("#[Maison][Présences][$user]#");
$distUser = mg::getVar("dist_Tel-$user", -1);

// Sortie et nettoyage si user présent
if ($userPresent || $distUser <= 0) {
//	mg::messageT('', "'$user' PRESENT, rien à faire");
	mg::unsetVar("_covidOldDist_$user");
	mg::unsetVar("_covidEtat_$user");
	return;
}

$timeEnvoi = '';
$geofence = mg::getCmd("#[Sys_Présence][*Map Présence][$user]#");
$oldDistUser = mg::getVar("_covidOldDist_$user", 0);
$etatUser =	mg::getVar("_covidEtat_$user", -1);

// User non présent et en entrainement
if (strpos($geofence, 'Pas de SSID') !== false && (strpos($geofence, 'entrainement') !== false || strpos($geofence, 'inactif') !== false)) {

	// Sortie Home
	if ($etatUser == -1 && $distUser > 0.15) {
		$timeEnvoi = time();
		$etatUser = 0;
		mg::messageT('', "Attestation du début de sortie ($oldDistUser km => $distUser km) pour $user à ".date('H\:i', $timeEnvoi));
	}
	// Plus de 1 km
	elseif ($etatUser == 0 && $oldDistUser < 1 && $distUser > 1) {
		$etatUser = 1;
		$timeEnvoi = time()-500;
		mg::messageT('', "Attestation pour éloignement ($oldDistUser km => $distUser km) > 1 km pour $user à ".date('H\:i', $timeEnvoi));
	}
	// RETOUR moins de 1 km
	elseif ($etatUser == 1 && $oldDistUser > 1 && $distUser < 1) {
		$etatUser = 2;
		$timeEnvoi = time()-500;
		mg::messageT('', "Attestation pour rapprochement ($oldDistUser km => $distUser km) < 1 km pour $user à ".date('H\:i', $timeEnvoi));
	}
}

envoi:
$equipCovid = "#[Maison][Covid $user]#";
if ($timeEnvoi && $user != 'NR') {
$heure = date('H\:i', $timeEnvoi);
mg::setInf($equipCovid, 'Heure attestation', $heure);
mg::setCmd($equipCovid, 'Envoi motif SPORT_ANIMAUX');
}

if ($user != '') {
	mg::setVar("_covidOldDist_$user", $distUser);
	mg::setVar("_covidEtat_$user", $etatUser);
}

?>