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
	$tabConso = mg::getTabSql('_tabConso');

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
	if ($timerMinMax && $gestionMinMax == 1 && mg::existCmd($equipement, 'Consommation')) {
		$infConso = mg::mkCmd($equipement, 'Consommation');
		$consommation = ($recalculConso == 1) ? $consoCalculee : floatval(mg::getCmd($infConso));
		$minConsoWeek = floatval(mg::getExp("minBetween($infConso, 7 day ago, now)"));
		$consoDay = ($consommation - $minConsoWeek) / 7;
		$min = max(round($consommation - 5), 0);
		$max = round($consommation + $consoDay + 5, 0) + ($consoDay < 1 ? 10 : 0); // REequerrage/rattrapage quotidien ????
		mg::setMinMaxCmd($equipement, 'Consommation', $min, $max);
		if (!$recalculConso) { mg::setValSql('_tabConso', $equipement, '', 'consoCalculee', 0); }
	}

	if (!mg::existCmd($equipement, 'Puissance')) continue;
	// --------------------------------------------------- PUISSANCE --------------------------------------------------
	if ($recalculPuissance == 1) {
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
	if ($recalculConso == 1 && $puissance > 0) {
		$consoCalculee += $puissance * $cron/60/1000;
		mg::setValSql('_tabConso', $equipement, '', 'consoCalculee', $consoCalculee);
		mg::setInf($equipement, 'Consommation', $consoCalculee);
		mg::messageT('', ". $nomAff : old/New Consommation calculée : {$detailsConso['consoCalculee']} / $consoCalculee");
	}
}

?>