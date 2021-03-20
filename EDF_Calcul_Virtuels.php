<?php
/**********************************************************************************************************************
EDF_Calcul_Virtuels - 177

Calcul autonomee de la consommation et de la puissance des équipements de "tabConsos" qui en sont dépourvus.

- Pour l'utilser il suffit de créer un virtuel en "dupliquant" l'équipement concerné et d'ajouter les commandes "Consommation" et "Puissance"
- De l'indexer dans le tableau de paramètrage "tabConsos" (sans oublier de renseigner les champs)

En complément la routine positionne les min - max des consommations pour aider au filtrage des valeurs erronées.

NB : Si vous désirez avoir un retour en temps réel sur le widget de l'équipement, mettez un déclencheur sur l'état de cet équipement.
	Faute de quoi la mise à jour de l'affichage s'effectuera sur le déclenchement du cron, soit toutes les mn.
**********************************************************************************************************************/

//N° des scénarios :

// Variables :
	$cron = 1; // Valeur du cron en minute (celui ci doit OBLIGATOIREMENT ETRE "* * * * *" ) pour le calcul de la consommation
	$tabConso = (array)mg::getVar('tabConso');

//Paramètres :

/**********************************************************************************************************************
**********************************************************************************************************************/
foreach ($tabConso as $equipement => $detailsConso) {
	$nomAff = mg::ExtractPartCmd($equipement, 2);
	$type = $detailsConso['type'];
	$recalculConso = $detailsConso['recalculConso'];
	$consoCalculee = floatval($detailsConso['consoCalculee']);
	$recalculPuissance = $detailsConso['recalculPuissance'];
	$puissanceEstimeeMax = floatval($detailsConso['puissanceEstimeeMax']);
	$gestionMinMax = $detailsConso['gestionMinMax'];
	// ------------------------------------- GESTION DES MIN / MAX DE CONSOMMATION ------------------------------------
	if ($gestionMinMax == 'true' && mg::existCmd($equipement, 'Consommation')) {
		$infConso = mg::mkCmd($equipement, 'Consommation');
		$consommation = mg::getCmd($infConso);
		$consoDay = intval(mg::getExp("minBetween($infConso,7 day ago, now)")); 
		$consoDay = max(($consommation - $consoDay)/7, 2); 
		$min = max(round($consommation-5), 0);
		$max = round($consommation+$consoDay*1.5+5, 0);
		mg::setMinMaxCmd($equipement, 'Consommation', $min, $max);
		if (!$recalculConso) { $tabConso[$equipement]['consoCalculee'] = ''; }
		if ($recalculPuissance || $recalculConso) {
		}
	}
	
	// --------------------------------------------------- PUISSANCE --------------------------------------------------
	if ($recalculPuissance == 'true' && mg::existCmd($equipement, 'Puissance')) {
		$maxValue = max(mg::getMinMaxCmd($equipement, 'Etat', 'max'), 99);
		$etat = mg::getCmd($equipement, 'Etat');
		$puissance = $puissanceEstimeeMax * (($etat != 1) ? $etat/$maxValue : 1);
		mg::setInf($equipement, 'Puissance', round($puissance,3));
		if ($recalculConso && $puissance > 0) {
			mg::messageT('', ". $nomAff : New puissance calculée : $puissance");
		}
	}
	
	// -------------------------------------------------- CONSOMMATION ------------------------------------------------
	if ($recalculConso == 'true' && mg::existCmd($equipement, 'Puissance')) {
		$puissance = mg::getCmd($equipement, 'Puissance');
		if ($puissance > 0) {
			$consoCalculee += $puissance * $cron/60/1000;
			$tabConso[$equipement]['consoCalculee'] = $consoCalculee;
			mg::setInf($equipement, 'Consommation', $consoCalculee); 
			mg::messageT('', ". $nomAff : old/New Consommation calculée : {$detailsConso['consoCalculee']} / $consoCalculee");
		}
	}
}
mg::setVar('tabConso', $tabConso);

?>