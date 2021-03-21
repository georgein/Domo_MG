<?php
/**********************************************************************************************************************
Volets Jour_Nuit - 29
Activé par volets jour, désactivé à l'aube par volets nuit
Gestion des volets en journée selon leurs paramétrages TabVolets

Paramètrable dans le tableau sur type d'action, hauteur ouvert/fermé du volet, vitesse du vent,
Paramètrage pour azimut, hauteur min/max du soleil et luminosité dans le eparamètrage général
Action dispo :
	Ouverture de fenêtre
	soleil génant
	vent fort
	Reveil Inhibition volet selon l'heure du réveil
	Ouverture du d'office au jour de l'étage OU sur mouvement si Occupé ET chambre utilisée (UNIQUEMENT EN JOURNEE)

**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$InfLumExt, $infPresenceEtg, $$infMvmtEtg
//	$equipReveil, $equipMvmtSalon, $equipMeteoFrance
// N° des scénarios :

//Variables :
	$alarme = mg::getVar('Alarme', 0);
	$nuitSalon = mg::getVar('NuitSalon');
	$nuitExt = mg::getVar('NuitExt');
	$saison = mg::getVar('Saison');
	$tabVolets = (array)mg::getVar('tabVolets');
	$nbMvmtSalon = mg::getCmd($equipMvmtSalon, 'NbMvmt');
	$heureReveil = mg::getVar('_Heure_Reveil');
	$ventFort = mg::getCmd($equipMeteoFrance, 'VentFort');

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$logVolets = mg::getParam('Log', 'volets');

	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');
	$latitude = mg::getParam('Volets', 'latitude');
	$longitude = mg::getParam('Volets', 'longitude');
	$lumMin = mg::getParam('Volets', 'lumMin');
	$azimuthMinSoleil = mg::getParam('Volets', 'azimuthMinSoleil');
	$azimuthMaxSoleil = mg::getParam('Volets', 'azimuthMaxSoleil');
	$hauteurMaxSoleilEte  = mg::getParam('Volets', 'hauteurMaxSoleilEte');
	$hauteurMaxSoleil = mg::getParam('Volets', 'hauteurMaxSoleil');
	$hauteurMinSoleil = mg::getParam('Volets', 'hauteurMinSoleil');
	$periodeAlerteFenetre =	mg::getParam('Volets', 'periodeAlerteFenetre');
	$timeoutAlerteFenetre =	 mg::getParam('Volets', 'timeoutAlerteFenetre');
	$destinatairesAlerteFenetre = mg::getParam('Volets', 'destinatairesAlerteFenetre');
	$seuilNbMvmt = mg::getParam('Lumieres', 'seuilNbMvmt');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
global $debug; $debugVolet; $debugVolet = false; // Pour neutraliser l'action sur les volets

//=====================================================================================================================
mg::messageT('', "! ********************************* CALCUL DES CONDITIONS DE BASES *******************************");
//=====================================================================================================================
// Condition Saison
$cdEte = ($saison == 'ETE') ? 1 : 0;
mg::Message('', "cdEte ==> Saison : $saison ==> " . (int)$cdEte);

// CD SOLEIL
$soleil = mg::Soleil(time(),  $latitude, $longitude);
$hauteurSoleil = round($soleil['altitude']);
$azimutSoleil = round($soleil['azimuth']);
$lum_ExterieurAvg = scenarioExpression::max($InfLumExt, '15 min');

$hauteurMaxSoleil = $cdEte ? $hauteurMaxSoleilEte : $hauteurMaxSoleil;
$cdSoleil = intval( $hauteurSoleil < $hauteurMaxSoleil && $hauteurSoleil < $hauteurMaxSoleil && $azimutSoleil > $azimuthMinSoleil && $azimutSoleil < $azimuthMaxSoleil && $lum_ExterieurAvg > $lumMin);
mg::MessageT('', ". cdSoleil : $cdSoleil ==> Auteur du Soleil : ($hauteurSoleil) < $hauteurMaxSoleil - Azimuth : $azimuthMinSoleil < ($azimutSoleil) < $azimuthMaxSoleil - Lum $lum_ExterieurAvg > $lumMin");

// Aurore pondérée
$aurore = min($heureReveil, mg::getVar('_Aurore'));

// CD VOLET NUIT
mg::message('', "Aurore à " . date('H\hi\m\n', $aurore));
$cdVoletsNuit = mg::TimeBetween(strtotime($timeVoletsNuit), time(), $aurore);
mg::message('', "cdVoletsNuit ==> $cdVoletsNuit");

// CD INHIB VOLETS REVEIL (cdInhibVoletsReveil DOIT succéder à cdVoletsNuit d'ou les '+900'
$cdInhibVoletsReveil = mg::TimeBetween($aurore, time(), $heureReveil+2*3600);
mg::message('', "cdInhibVoletsReveil ==> $cdInhibVoletsReveil");

//=====================================================================================================================
mg::messageT('', "! VOLETS GENERIQUES");
//=====================================================================================================================

if ($alarme || $cdVoletsNuit || ($nuitExt == 2 && !$cdInhibVoletsReveil)) { 
mg::messageT('', ". ***************************** FERMETURE VOLETS EN ALARME OU A L'AUBE ***************************");
	if (mg::getVar('_VoletGeneral') != 'D') {
		mg::Message($logTimeLine, "Volets - Fermeture générale, time > $timeVoletsNuit | Alarme | Aube.");
		mg::VoletsGeneral('Salon, Chambre, Etage', 'D');
	}
}

elseif ( !$alarme && $nbMvmtSalon >= $seuilNbMvmt  && $cdInhibVoletsReveil) {
mg::messageT('', ". ***************** OUVERTURE GENERALE SALON APRES LE REVEIL AU PREMIER MOUVEMENT ****************");
	if (mg::getVar('_VoletGeneral') != 'M') {
		mg::Message($logTimeLine, "Volets - Ouverture générale du matin.");
		mg::VoletsGeneral('Salon', 'M');
	}
}

foreach ($tabVolets as $cmd => $details_Volet) {

	//=================================================================================================================
	mg::messageT('', "! TRAITEMENT DE $cmd");
	//=================================================================================================================
	$nomAff = $cmd;
	$nomZone = trim($details_Volet['zone']);
	$sliderSoleilGenantEte = intval($details_Volet['genantEte']);
	$sliderSoleilGenantHiver = intval($details_Volet['genantHiver']);
	if ($cdEte) { $sliderSoleilGenant = $sliderSoleilGenantEte; }
	else { $sliderSoleilGenant = $sliderSoleilGenantHiver; }

	if ($sliderSoleilGenantHiver == 99 || $sliderSoleilGenantHiver == 0) { $sliderSoleilGenant = 99; }
	mg::message('', "SliderSoleilGenant : $sliderSoleilGenant");

	$sliderSoirETE_O = intval($details_Volet['soir']);
	$sliderNuitETE_O = intval($details_Volet['nuit']);
	$ventMax = intval($details_Volet['vent']);
	$voletReveil = trim($details_Volet['reveil']);
	$duree = intval($details_Volet['duree']); // si == 0 => pas de volet
	$voletInverse =	 trim($details_Volet['inverse']);
	$alerteOuvert = trim($details_Volet['alerteOuvert']);
	$ouvrant =	 trim($details_Volet['ouvrant']);

	$equipVolet = "[$nomZone][$cmd]";
	$equipOuverture = "[$nomZone][$ouvrant".']';

	$sliderCourant = 0;
	if ($duree > 0) {
		$sliderCourant = mg::getCmd("#[$nomZone][Ouvertures][$cmd"." Etat]#");
		if ($sliderCourant > 99) { $sliderCourant = $sliderCourant - 100; }
		
		$slider =  $sliderCourant >= 99 ? 99 : $sliderCourant;
		$slider = $sliderCourant <= 0.1 ? 0.1 : $sliderCourant;
		$messageAff = "Rien faire - Courant = $sliderCourant";
	}

	// Condition sur vent fort pour CE volet
	$cdVentVort = 0;
	if($ventMax != 0) {
			$cdVentVort = ($ventFort && $ventMax > 0) ? 1 : 0;
		mg::Message('', "CdVentVort ==> $cdVentVort");
	 }

	// Condition fenêtre Ouverte
	if ($alerteOuvert != '!') {
		$cdOuvert = mg::getCmd($equipOuverture, 'Ouverture');
	}
	else { $cdOuvert = 1; }
	mg::message('', "CdOuvert ==> - $cdOuvert");

	if ( !$alarme && $nbMvmtSalon >= $seuilNbMvmt && $cdInhibVoletsReveil ) {
		//=============================================================================================================
		mg::messageT('', ". OUVERTURE INDIVIDUELLE SALON APRES LE REVEIL AU PREMIER MOUVEMENT");
		//=============================================================================================================
			$slider = 99; $messageAff = "Ouverture générale du matin.";
	//		mg::Message($logTimeLine, $messageAff);
		}

	// Traité en journée et APRES le REVEIL
	if (!$alarme && !$nuitExt && !$cdInhibVoletsReveil) {
		//=============================================================================================================
		mg::messageT('', ". SOLEIL GENANT");
		//=============================================================================================================
		if (!$nuitSalon && $cdSoleil)
		{
			$slider = $sliderSoleilGenant; $messageAff = "Soleil Génant";
		}
		elseif ($nuitSalon || !$cdSoleil && !$cdOuvert) { ///////////////////////////////
			$slider = 99; $messageAff = "Fin Soleil Génant";
		}
	}

	if ($alarme || $cdVoletsNuit || ($nuitExt == 2 && !$cdInhibVoletsReveil)) {
		//=============================================================================================================
		mg::messageT('', ". FERMETURE VOLET A 23:00 OU A L'AUBE OU EN ALARME");
		//=============================================================================================================
		$slider = -10; $messageAff = "> $timeVoletsNuit | Alarme | Aube";
		if ($voletInverse == '-') { $slider = 110;	$messageAff = "$messageAff ==> Fin"; }
	}

	// POUR L'ETE hors zone du REVEIL et uniquement la nuit
	if(!$alarme && $cdEte && $nuitExt == 1 && !$cdInhibVoletsReveil) {
		//=============================================================================================================
		mg::messageT('', ". OUVERTURE/FERMETURE FENETRE");
		//=============================================================================================================
		$sliderOuvert = $sliderCourant;
		if ($nuitSalon == 1) { $sliderOuvert = $sliderSoirETE_O; } // Le soir (Si fenêtre ouverte)
		if ($nuitSalon == 2) { $sliderOuvert = $sliderNuitETE_O; } // La nuit (Si fenêtre ouverte)
		if ($cdOuvert) {
			$slider = $sliderOuvert; $messageAff = "Fenêtre ouverte.";
			if ($voletInverse == '-') { $slider = 99; $messageAff = "$messageAff ==> Fin"; }
		}
		elseif ($cdVoletsNuit) {
			$slider = 0.1; $messageAff = "Fenêtre fermée.";
		}
	}

	// On inhibe tout mouvement selon l'heure pour les volets flagué 'R'.
	if (!$alarme && $voletReveil == 'R' && $cdInhibVoletsReveil) {
		//=============================================================================================================
		mg::messageT('', ". INHIBITION AVANT/APRES REVEIL");
		//=============================================================================================================
		$slider = $sliderCourant; $messageAff = "Inhibition autour du Réveil / Rien faire"; continue;
	}
	fin:

	if ($cdOuvert && $cdVentVort && $ventMax > 0) {
		//=============================================================================================================
		mg::messageT('', ". FERMETURE VOLET SI VENT FORT");
		//=============================================================================================================

		if ($voletInverse == '-') { $slider = 110; } else { $slider = -10; }
		$messageAff = "Vent fort / Nuit";
	}

	if ($alerteOuvert == 'A' && $cdOuvert) {
		//=============================================================================================================
		mg::messageT('', ". ALERTE FENETRE OUVERTE");
		//=============================================================================================================
		$lastMvmtEtg = round(mg::lastMvmt($infMvmtEtg, $NbMvmtEtg)/60);
		if ($cdVoletsNuit && $lastMvmtEtg > 8*60) {
			if (strpos($nomZone, 'Etg') !== 0) { $nomFenetre = "une fenêtre de l'étage"; } else { $nomFenetre = "la fenêtre de $cmd"; }
			$heureFin = strtotime($timeVoletsNuit) + $timeoutAlerteFenetre*60;
			if (time() <= $heureFin) {
				mg::message('', "---------------ATTENTION $nomFenetre est ouverte, Veuillez la fermer.---------------------");
				$slider = $sliderCourant;
				mg::Alerte($cmd, $periodeAlerteFenetre*60, $heureFin, $destinatairesAlerteFenetre, "ATTENTION $nomFenetre est ouverte, Veuillez la fermer.");
			} else {
				mg::Alerte($nomFenetre, 0); // Annulation Alerte
				mg::setCron('', '*/5 * * * * *');
			}
		}
	}

	// TRAITEMENT FINAL
		//=============================================================================================================
//		mg::messageT('', ". FIN $cmd");
		//=============================================================================================================
	if ($duree > 0) {
		if(abs($slider - $sliderCourant) < 5) { $sens = "Rien faire"; }
		else { ($slider < $sliderCourant) ? $sens = "Descente" : $sens = "Montée"; }
		$message = "$sens pour $nomAff ($messageAff) Slider $sliderCourant => $slider.";
		mg::Message('', "---------------------------------------------- $message -----------------------------------");
		// Activation volet si différence sensible ou min forcé ou max forcé
		if (abs($slider - $sliderCourant) >= 5 || $slider < 0.1 || $slider > 99) { //////////////////////////////// <= et >=
			if (!$debugVolet) {
				$debug = false;
				mg::VoletRoulant($nomZone, $cmd, 'Slider', $slider);
				$debug = true;
			}
			// On ne logue que les demande "non forcée)
			if (strpos($message, 'Rien faire') !== false) {continue; }
			if (($slider <= 99 && $slider >= 0.1) && ($slider != 110 && $slider != -10)) { mg::Message($logVolets, $message); }
		}
//		mg::message($logTimeLine, $message);
	}
} //Fin boucle Volets
mg::MessageT('', ". cdSoleil : $cdSoleil ==> HauteurSoleil : $hauteurMinSoleil < ($hauteurSoleil) < $hauteurMaxSoleil - Azimuth : $azimuthMinSoleil < ($azimutSoleil) < $azimuthMaxSoleil");

?>