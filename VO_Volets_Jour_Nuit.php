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
	$vitesseVent = max(mg::getCmd($equipMeteoFrance, 'Rafales Réelles'), mg::getCmd($equipMeteoFrance, 'Vitesse Rafales METAR'));

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
mg::messageT('', "! CALCUL DES CONDITIONS DE BASE");
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
$cdInhibVoletsReveil = mg::TimeBetween($aurore+900, time(), $heureReveil+2*3600);
mg::message('', "cdInhibVoletsReveil ==> $cdInhibVoletsReveil");

if ($alarme || $cdVoletsNuit ) {
	//=====================================================================================================================
	mg::messageT('', ". FERMETURE VOLETS DE NUIT || ALARME.");
	//=====================================================================================================================
	if (mg::getVar('_VoletGeneral') != 'D') {
		mg::Message($logTimeLine, "Volets - Fermeture générale, time > $timeVoletsNuit | Alarme.");
		mg::VoletsGeneral('Salon, Chambre, Etage', 'D', 1); 
	}
	
} elseif ( !$alarme && $nbMvmtSalon >= $seuilNbMvmt  && $cdInhibVoletsReveil) {
	//=====================================================================================================================
	mg::messageT('', ". OUVERTURE GENERALE SALON APRES LE REVEIL AU PREMIER MOUVEMENT.");
	//=====================================================================================================================
	if (mg::getVar('_VoletGeneral') != 'M') {
		mg::Message($logTimeLine, "Volets - Ouverture générale du matin au premier mouvement.");
		mg::VoletsGeneral('Salon', 'M');
	}
}

foreach ($tabVolets as $cmd => $details_Volet) {

	$nomAff = $cmd;
	$nomAff = str_replace('SàM', 'Salle à Manger ', $nomAff);
	$nomAff = str_replace('Volet', 'Fenêtre ', $nomAff);
	$nomAff = str_replace('Etg', 'Etage ', $nomAff);
	$nomAff = str_replace('RdC', 'Rez de Chaussée ', $nomAff);
	$nomAff = str_replace('SdB', 'Salle de Bain ', $nomAff);
	$nomAff = str_replace('WC', 'Toilettes ', $nomAff);
	//=================================================================================================================
	mg::messageT('', "! TRAITEMENT DE $cmd - $nomAff");
	//=================================================================================================================
	
	$messageAff = '-- Aucun --';
	$nomZone = trim($details_Volet['zone']);

	if ($cdEte) { $sliderSoleilGenant = intval($details_Volet['genantEte']); }
	else { $sliderSoleilGenant = intval($details_Volet['genantHiver']); }
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

	$sliderCourant = 0.1;
	if ($duree > 0) {
		$sliderCourant = mg::getCmd("#[$nomZone][Ouvertures][$cmd"." Etat]#");
		if ($sliderCourant > 99) { $sliderCourant = $sliderCourant - 100; }
		
		$slider =  $sliderCourant > 99 ? 99 : $sliderCourant;
		$slider = $sliderCourant < 0.1 ? 0.1 : $sliderCourant;
		$messageAff = "Rien faire - Courant = $sliderCourant";
	}

	// Condition sur vent fort pour CE volet
	$cdVentVort = ($vitesseVent > $ventMax && $ventMax > 0) ? 1 : 0;
	mg::Message('', "CdVentVort : $vitesseVent km/h > $ventMax km/h ==> $cdVentVort");

	// Condition fenêtre Ouverte
	$cdOuvert = 1;
	if ($alerteOuvert != '!') {
		$cdOuvert = mg::getCmd($equipOuverture, 'Ouverture');
	} elseif ($sliderCourant <= 99) { $cdOuvert = 0; }

	mg::message('', "CdOuvert ==> $cdOuvert");

	if ( !$alarme && !$nuitExt && $nbMvmtSalon >= $seuilNbMvmt && $cdInhibVoletsReveil ) {
		//=============================================================================================================
		mg::messageT('', ". OUVERTURE INDIVIDUELLE SALON APRES LE REVEIL AU PREMIER MOUVEMENT");
		//=============================================================================================================
			$slider = 99; $messageAff = "Ouverture générale du matin.";
	//		mg::Message($logTimeLine, $messageAff);
		}

	// Traité en journée et APRES le REVEIL
	if (!$alarme && $sliderSoleilGenant > 0 && !$nuitExt && !$cdInhibVoletsReveil) {
		//=============================================================================================================
		mg::messageT('', ". SOLEIL GENANT");
		//=============================================================================================================
		if (!$nuitSalon && $cdSoleil) {
			$slider = $sliderSoleilGenant; $messageAff = "Soleil Génant";
		}
		elseif ($nuitSalon || !$cdSoleil && !$cdOuvert) { 
			$slider = 99; $messageAff = "Fin Soleil Génant";
		}
	}

	if ($nuitExt == 2) {
		//=============================================================================================================
		mg::messageT('', ". FERMETURE VOLET A L'AUBE");
		//=============================================================================================================
		$messageAff = "FERMETURE VOLET A L'AUBE";
		$slider = -10; 
		if ($voletInverse == 'true') { $slider = 110; }	
		goto fin;
	}

	// POUR L'ETE hors zone du REVEIL et uniquement la nuit
	if(!$alarme && $cdEte && $nuitExt == 1 && !$cdInhibVoletsReveil) {
		//=============================================================================================================
		mg::messageT('', ". OUVERTURE/FERMETURE FENETRE");
		//=============================================================================================================
		$sliderOuvert = $sliderCourant;
		if ($nuitSalon == 1) { $sliderOuvert = $sliderSoirETE_O; } // Le soir (Si fenêtre ouverte)
		if ($nuitSalon == 2) { $sliderOuvert = $sliderNuitETE_O; } // La nuit (Si fenêtre ouverte)
		
		// Fenêtre ouverte
		if (!$cdOuvert) {
			$messageAff = "Fenêtre ouverte.";
			$slider = $sliderOuvert; 
		// Fenêtre fermée de nuit
		} elseif ($cdVoletsNuit) {
			$messageAff = "Fenêtre fermée.";
			$slider = 0.1; 
			if ($voletInverse == 'true') { $slider = 110; } 
		}
	}

	// On inhibe tout mouvement selon l'heure pour les volets flagué 'R'.
	if (!$alarme && $voletReveil == 'R' && $cdInhibVoletsReveil) {
		//=============================================================================================================
		mg::messageT('', ". INHIBITION AVANT/APRES REVEIL");
		//=============================================================================================================
		$slider = $sliderCourant; $messageAff = "Inhibition autour du Réveil / Rien faire"; continue;
	}

	if (!$cdOuvert && $cdVentVort) {
		//=============================================================================================================
		mg::messageT('', ". FERMETURE VOLET SI VENT FORT");
		//=============================================================================================================

		if ($voletInverse) { $slider = 110; } else { $slider = -10; }
		$messageAff = "Vent fort > $vitesseVent km/h / Nuit";
	}

	if ($cdVoletsNuit && ($alerteOuvert != '' && !$cdOuvert)) {
		//=============================================================================================================
		mg::messageT('', ". ALERTE FENETRE OUVERTE");
		//=============================================================================================================
		// Si étage occupé OU mouvement de moins de 3 heures
		$lastMvmtEtg = round(mg::lastMvmt($infMvmtEtg, $NbMvmtEtg)/60);
		if (strpos($nomZone, 'Etg') !== 0 || $lastMvmtEtg > 3*60) {
			
			// Envoi de l'alerte volet ouvert
			$heureFin = strtotime($timeVoletsNuit) + $timeoutAlerteFenetre*60;
			if (time() <= $heureFin) {
				$messageAlerte = "ATTENTION $nomAff est ouverte, Veuillez la fermer.";
				mg::Alerte($cmd, $periodeAlerteFenetre/10, $heureFin, $destinatairesAlerteFenetre, $messageAlerte);
			} else {
				mg::Alerte($cmd, -1); // Annulation Alerte
			}
		}
	} else {
		mg::Alerte($cmd, -1); // Annulation Alerte
	}
	
	fin:
	// TRAITEMENT FINAL
	if ($duree > 0) {
		if(abs($slider - $sliderCourant) < 5) { $sens = "Rien à faire"; }
		else { ($slider < $sliderCourant) ? $sens = "Descente" : $sens = "Montée"; }
		$message = "$sens pour $cmd ($messageAff) Slider $sliderCourant => $slider.";
		mg::Message('', "---------------------------------------------- $message -----------------------------------");
		// Activation volet si différence sensible ou min forcé ou max forcé
		if (abs($slider - $sliderCourant) >= 5 || $slider < 0.1 || $slider > 99) { 
			if (!$debugVolet) {
				$debug = false;
				mg::VoletRoulant($nomZone, $cmd, 'Slider', $slider);
				$debug = true;
			}
			// On ne logue que les demande "non forcée)
			if (strpos($message, 'Rien faire') !== false) {continue; }
			if (($slider <= 99 && $slider >= 0.1) && ($slider != 110 && $slider != -10)) { mg::Message($logVolets, $message); }
		}
	}
} //Fin boucle Volets

mg::MessageT('', ". cdSoleil : $cdSoleil ==> HauteurSoleil : $hauteurMinSoleil < ($hauteurSoleil) < $hauteurMaxSoleil - Azimuth : $azimuthMinSoleil < ($azimutSoleil) < $azimuthMaxSoleil");

mg::setCron('', '*/5 * * * * *'); // En dernière ligne pour écraser cron des alertes

?>