<?php
/**********************************************************************************************************************
Piscine - 114
Gestion de la circulation selon les horaires définis (basé sur TempPiscine / 2, x cycle et décalage temporel).
Met en route la piscine en continue si en marche forcée.
Hors du mode horaire :
	Met en route la piscine si la température piscine < $piscineTempGel selon $CycleForcé.
	Met en route la piscine si la température VentFort selon $CycleForcé.
	Les différents mode de marche forcée sont suspendus durant le cycle temporel.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$infTempPiscine, $infPiscineForcee
//	$equipPiscine, $equipCompresseur, $equipMeteoFrance, $equipOndilo

// N° des scénarios :

//Variables :
	$ventFort = mg::getcmd($equipMeteoFrance, 'VentFort');

// Paramètres :
	$piscineRatioDuree = mg::getParam('Piscine', 'ratioDuree');
	$piscineNbCyclesHC = mg::getParam('Piscine', 'nbCyclesHC');	// Heures creuses : 2H00-7H00 13H00-16H00
	$piscineTempGel = mg::getParam('Piscine', 'tempGel');			// Température de marche forcée de la piscine.
	$piscineCycleForce = mg::getParam('Piscine', 'cycleForce');	// Cycle de fonctionnement (en heure) en marche forcée (2 ==> 1 heure sur 2).
	$logTimeLine = mg::getParam('Log', 'timeLine');

	$phMin = 7.00;
	$phMax = 7.40;
	$redoxMin = 650;
	$redoxMax = 750;
	$batterieMin = 30;

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
	$tempPiscine = mg::getCmd($infTempPiscine);
	$ph = mg::getCmd($equipOndilo, 'Ph');
	$redox = mg::getCmd($equipOndilo, 'Redox');
	$batterie = mg::getCmd($equipOndilo, 'Niveau de la batterie');
	mg::setCmd($equipOndilo, 'Rafraichir');

/*---------------------------------------------------------------------------------------------------------------------
										SURVEILLANCE PH et REDOX via ICO ONDILO
---------------------------------------------------------------------------------------------------------------------*/
$notif_ICO = 'Info Piscine Ensuèse : ';

if ($ph < $phMin) {
	$notif_ICO .= "Le Phi est trop bas, veuillez ajouter du Phi pluse ! ";
} else if ($ph > $phMax) {
$notif_ICO .= "Le Le Phi est trop haut ! ";
}

$notif_ICO .= ", ";
if ($redox < $redoxMin) {
	$notif_ICO .= "Le Redox est trop bas, veuillez rajouter du chlore ! ";
} else if ($redox> $redoxMax) {
	$notif_ICO .= "Le Redox est trop haut ! ";
}

if ($batterie < $batterieMin) {
	$notif_ICO .= ", La batterie de l'Ondilo est inférieure à $batterieMin %, veuillez le recharger !";
}

$notif_ICO .= " La température de l'eau est de $tempPiscine °C.";

mg::setVar('_Notif_ICO', $notif_ICO);

/*---------------------------------------------------------------------------------------------------------------------
										Gestion marche forcée de la piscine
---------------------------------------------------------------------------------------------------------------------*/
$etatPiscineForcee = mg::getCmd($infPiscineForcee);

$oldTxt = mg::getCmd($equipPiscine, 'HeuresPiscine');

$piscineModeForce = 0;
$txt = '';

if ($tempPiscine <= $piscineTempGel || $ventFort || $etatPiscineForcee) {

mg::Message('', "----------------------------------- GESTION MARCHE FORCEE -----------------------------------------");

	// Message du mode forcé de la piscine
	$cycle = $etatPiscineForcee ? 1 : ((date('H', time()) % $piscineCycleForce)+1);
	mg::message('', " - Cycle : $cycle / $piscineCycleForce.");

	if ($tempPiscine <= $piscineTempGel) { $txt = 'Gel'; }
	else if ($ventFort ) { $txt = 'Vent fort'; }
	$txt = "Mode $txt ($cycle/$piscineCycleForce).";

	if ($cycle == 1 && $etatPiscineForcee) {
		$txt = 'Mode Marche forcée';
	// Au 1er démarrage ou changement a "forcé"
		if ($oldTxt != $txt) {
			mg::message($logTimeLine, "Piscine - Mode Marche forcée.");
		}
	}
	$piscineModeForce = 1;

	// Gestion des cycles
	if ( $cycle == 1) {
		if (!mg::getCmd($equipCompresseur, 'Etat')) {
			mg::setCmd($equipCompresseur, 'On');
		}
	} else {
		if (mg::getCmd($equipCompresseur, 'Etat') || mg::getCmd($equipCompresseur, 'Puissance') > 100) {
		mg::setCmd($equipCompresseur, 'Off');
		}
	}
}

// Message d'Arrêt de la marche forcée
elseif (!$piscineModeForce && strpos($oldTxt, 'Mode') !== false) {
	if (mg::getCmd($equipCompresseur, 'Etat') || mg::getCmd($equipCompresseur, 'Puissance') > 100) {
		mg::setCmd($equipCompresseur, 'Off');
		if ($oldTxt != $txt && $etatPiscineForcee) {
			mg::message($logTimeLine, "Piscine - Arrêt du mode Marche forcée.");
		}
	}
}

/*-----------------------------------------------------------------------------------------------------------------------------
										Gestion des horaires de la piscine
-----------------------------------------------------------------------------------------------------------------------------*/
// Si pas marche forcée
fin:
if (!$piscineModeForce) {
	$dureeCycleFiltration = $tempPiscine / $piscineRatioDuree * 3600 / $piscineNbCyclesHC;mg::message('', $dureeCycleFiltration);
	$dureeTotaleTxt = " (" . round($dureeCycleFiltration/3600 * $piscineNbCyclesHC, 1) . "h/Jour)";

	for ($i=1; $i<=$piscineNbCyclesHC; $i++) {
	$heureDeb = strtotime(mg::getParam('EDF', "deb_HC$i"));
		$heureFin = $heureDeb + $dureeCycleFiltration;

mg::Message('', "---------------------------------------- CYCLE ACTIF ----------------------------------------------");
		if (mg::timeBetween($heureDeb, time(), $heureFin+3600)) {
			// Démarrage
			if (time() < $heureFin) {
				$txt = "HC$i : " . date('H\:i', $heureDeb) . ' => ' . date('H\:i', $heureFin) . $dureeTotaleTxt;
				if (!mg::getCmd($equipCompresseur, 'Etat') || mg::getCmd($equipCompresseur, 'Puissance') < 100) {
					mg::setCmd($equipCompresseur, 'On');
					mg::message($equipCompresseur, "Piscine - Démarrage à " .  date('H\hi\m\n', time()) . " - fin à " . date('H\hi\m\n', $heureFin));
				}
			// Arrêt
			} else {
				if (mg::getCmd($equipCompresseur, 'Etat') || mg::getCmd($equipCompresseur, 'Puissance') > 100) {
					mg::setCmd($equipCompresseur, 'Off');
					mg::message($logTimeLine, "Piscine - Arrêtée le " . date('d\/m\/Y \à H\hi\m\n', time()));
				}
			}
		}

mg::Message('', "---------------------------------------- CYCLE SUIVANT ----------------------------------------------");
		if (mg::timeBetween(time(), $heureDeb, time()+12*3600)) {
			$txt = "HC$i : " . date('H\:i', $heureDeb) . ' => ' . date('H\:i', $heureFin) . $dureeTotaleTxt;
		}
	}
}

mg::Message('', "------------------------------------------- FIN ---------------------------------------------------");
mg::setInf($equipPiscine, 'HeuresPiscine', "$txt")
?>