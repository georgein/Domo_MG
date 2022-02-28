<?php
/**********************************************************************************************************************
function Menu_du_Soir_108()
Envoi des proposition de menu par SMS le soir
**********************************************************************************************************************/

// Infos, Commandes et Equipements :

// N° des scénarios :

//Variables :
	$tab_Viandes = array(
					'Blanc de poulet (120 grammes.)',
					'Boudin (100 grammes.)',
					'Steack haché (100 grammes.)',
					'Steak pavé (120 grammes.)',
					'Saucisse (120 grammes.)',
					'Oeufs au plat (2 oeufs.)',
					'Canard (120 grammes.)',
					'Omelette (2 oeufs.)',
	);

	$tab_Legumes = array(
				'Patte au beurre (200 grammes.)', // avec 2 t pour TTS ....
				'Patte champignons (200 grammes.)',
				'Purée de pomme de terre (200 grammes.)',
				'Pomme de terre à l\'eau (200 grammes.)',
				'Ecrasé de pomme de terre (200 grammes.)',
				'Pommes de terre rissolées (200 grammes.)',
				'Gratin de pomme de terre (200 grammes.)',
				'Lentilles (200 grammes.)',
				'Haricots verts (175 grammes.)',
				'Haricots jaunes (175 grammes.)',
				'Haricots Plats (175 grammes.)',
				'Epinards béchamel (200 grammes.)',
				'Courgettes (175 grammes.)',
				'Petits pois (175 grammes.)',
				'Riz (200 grammes.)'
	);
	
	$tab_PlatsComplets = array(
					'Patte Carbonara (350 grammes avec légumes)',
					'Riz cantonnais (350 grammes avec légumes)',
					'Tortilla (350 grammes avec légumes)',
					'Blanquette de veau (350 grammes avec légumes)',
					'Parmentier (350 grammes avec légumes.)',
);

		$alarme = mg::getVar('Alarme');

// Paramètres :
	$destinatairesMenu = mg::getParam('Confort', 'destinatairesMenu');			// Destinataires Menu du soir

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
// Pas de Menu du soir si en cours d'alarme
if ( $alarme > 0 ) { return; }

$menu = 'Proposition de repas du soir : ';

if (random_int( 0, 3) != 0) {
	$menu .= 'Viande : ' . $tab_Viandes[random_int( 0 , count($tab_Viandes) -1)];
	$menu .= ' - Légume : ' . $tab_Legumes[random_int( 0 , count($tab_Legumes) -1)];
} else {
	$menu .= ' - Plat préparé : ' . $tab_PlatsComplets[random_int( 0 , count($tab_PlatsComplets) -1)];
}

mg::Message($destinatairesMenu, $menu);

?>