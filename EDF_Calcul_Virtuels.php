<?php
/**********************************************************************************************************************
EDF_Calcul_Virtuels - 177

Positionne (une fois par heure) les min - max des consommations des équipements de "tabConsos" pour aider au filtrage des valeurs erronées.

Calcul autonomee de la consommation et de la puissance des équipements de "tabConsos" qui en sont dépourvus nativement.

- Pour l'utilser il suffit avecjMQTT de créer les commandes 'leurre' numérique "Consommation" et "Puissance".
- De l'indexer dans le tableau de paramètrage "tabConsos" (sans oublier de renseigner les champs)
**********************************************************************************************************************/

//N° des scénarios :

// Variables :
	$cron = 1; // Valeur du cron en minute (celui ci doit OBLIGATOIREMENT ETRE "* * * * *" ) pour le calcul de la consommation
	$tabConso = (array)mg::getVar('tabConso');

//Paramètres :

/**********************************************************************************************************************
**********************************************************************************************************************/
$timerMinMax = (mg::getTag('#minute#') == 05 || mg::declencheur('user'));
$puissance = 0;

foreach ($tabConso as $equipement => $detailsConso) {
	$nomAff = mg::ExtractPartCmd($equipement, 2);
	$type = $detailsConso['type'];
	$recalculConso = $detailsConso['recalculConso'];
	$consoCalculee = floatval($detailsConso['consoCalculee']);
	$recalculPuissance = $detailsConso['recalculPuissance'];
	$puissanceEstimeeMax = floatval($detailsConso['puissanceEstimeeMax']);
	$gestionMinMax = $detailsConso['gestionMinMax'];


	// ------------------------------------- GESTION DES MIN / MAX DE CONSOMMATION ------------------------------------
	if ($timerMinMax && $gestionMinMax == 'true' && mg::existCmd($equipement, 'Consommation')) {
	$infConso = mg::mkCmd($equipement, 'Consommation');
	$consommation = ($recalculConso == 'true') ? $consoCalculee : floatval(mg::getCmd($infConso));
		$consoDay = floatval(mg::getExp("minBetween($infConso,7 day ago, now)"));
		$consoDay = max(($consommation - $consoDay)/7, 2);
		$min = max(round($consommation-5), 0);
		$max = round($consommation+$consoDay+5, 0) * ($consoDay <= 0 ? 3 : 1); // REequerrage/rattrapage quotidien ????
		mg::setMinMaxCmd($equipement, 'Consommation', $min, $max);
		if (!$recalculConso) { $tabConso[$equipement]['consoCalculee'] = ''; }
		if ($recalculPuissance || $recalculConso) {
		}
	}

	if (!mg::existCmd($equipement, 'Puissance')) continue;
	// --------------------------------------------------- PUISSANCE --------------------------------------------------
	if ($recalculPuissance == 'true') {
		$puissance = mg::getCmd($equipement, 'Puissance');
		$maxValue = max(mg::getMinMaxCmd($equipement, 'Etat', 'max'), 99);
		$etat = mg::getCmd($equipement, 'Etat');
		$puissanceNew = $puissanceEstimeeMax * (($etat > 1) ? $etat/$maxValue : $etat);
		if ($puissance != $puissanceNew) {
			mg::setInf($equipement, 'Puissance', round($puissanceNew, 3));
			mg::messageT('', ". $nomAff : New puissance calculée : $puissanceNew");
		}
	}

	// -------------------------------------------------- CONSOMMATION ------------------------------------------------
	if ($recalculConso == 'true' && $puissance > 0) {
		$consoCalculee += $puissance * $cron/60/1000;
		$tabConso[$equipement]['consoCalculee'] = $consoCalculee;
		mg::setInf($equipement, 'Consommation', $consoCalculee);
		mg::messageT('', ". $nomAff : old/New Consommation calculée : {$detailsConso['consoCalculee']} / $consoCalculee");
	}
}
mg::setVar('tabConso', $tabConso);

?>