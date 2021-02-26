<?php
/**********************************************************************************************************************
EDF_CalculConso - 177

Calcul autonomee de la consommation des équipements de tabConso.
Si la commande 'Consommation' et/ou puissance n'existent pas il faut créer un virtuel 'clone' de l'équipement et les ajouter.
Si la puissance consommée n'est pas donnée par l'équipement, la puissance maximale peut être précisée dans le tableau de
paramètrage, elle sera pondérée avec le % de l'état de l'équipement.

Equerre les min - max des consommations.
**********************************************************************************************************************/

//N° des scénarios :

// Variables :
	$cron = 1; // cron en minute
	$tabConso = (array)mg::getVar('tabConso');

//Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
foreach ($tabConso as $equipement => $detailsConso) {
	$nomAff = mg::ExtractPartCmd($equipement, 2);
	$type = $detailsConso['type'];
	$recalculConso = boolval($detailsConso['recalculConso']);
	$consoCalculee = floatval($detailsConso['consoCalculee']);
	$recalculPuissance = boolval($detailsConso['recalculPuissance']);
	$puissanceEstimeeMax = floatval($detailsConso['puissanceEstimeeMax']);
	$gestionMinMax = $detailsConso['gestionMinMax'];

	// Gestion des min / max de consommation (conso + 1.5 jour de conso)
	if ($gestionMinMax) {
		$infConso = mg::mkCmd($equipement, 'Consommation');
		$consommation = mg::getCmd($infConso);
		$consoDay = intval(mg::getExp("minBetween($infConso,7 day ago, now)")); 
		$consoDay = max(($consommation - $consoDay)/7, 2); 
		mg::setMinMaxCmd($infConso, max(round($consommation-5), 0), round($consommation+$consoDay*1.5+5, 0));
	}

	// On ne traite que si $recalculConso ou $recalculPuissance et la commande 'Consommation' existe
	if ((!$recalculConso && !$recalculPuissance) || !mg::existCmd($equipement, 'Consommation')) { 
		$tabConso[$equipement]['consoCalculee'] = '';
		continue; 
	}

	// Récupération de la puissance réelle ou théorique
	if (mg::existCmd($equipement, 'Puissance')) { 
		$puissance = mg::getCmd($equipement, 'Puissance');
	} else { $puissance = $puissanceEstimeeMax; }

	// Calcul de l'état
	if (mg::existCmd($equipement, 'Etat')) { 
		$etat = mg::getCmd($equipement, 'Etat');
	} else { $etat = ($puissance != 0); }
	// Si pas en route on saute
	if ($etat == 0) { continue; }

	// Pondération / Enregistrement de la puissance consommée pondérée du maxValue
	$maxValue = max(mg::getMinMaxCmd($equipement, 'max'), 99);
	$puissance = $puissance * ($etat != 1 ? (mg::getCmd($equipement, 'Etat')/$maxValue*99) : 1);
	 mg::message('', "+++++++++++++ max = $maxValue - $puissance);
	if ($recalculPuissance) { mg::setInf($equipement, 'Puissance', $puissance); }
	
	// Calcul / enregistrement de la consommation
	if ($recalculConso) {
		$consoCalculee += $puissance * $cron/60/1000;
		$tabConso[$equipement]['consoCalculee'] = $consoCalculee;
		mg::setInf($equipement, 'Consommation', $consoCalculee); 
	}

mg::message('', ($recalculConso ? true : false) . ' - ' . ($recalculPuissance ? true : false));
	mg::messageT('', "! $nomAff => puissanceEstimeeMax / Calculée : $puissanceEstimeeMax / $puissance - Soit + " . $puissance * $cron/60/1000 . " kw/h => new consommation: ".$detailsConso['consoCalculee'].' kw/h');
}
mg::setVar('tabConso', $tabConso);

?>