<?php
/**********************************************************************************************************************
Lampes folles - 110
L'arrêt du scénario se fait en annulant la variable '_LampesFolles'.
Lance la sirène, fait clignoter les lampes du tableau de manière aléatoire selon $Periode et joue un son aléatoire sur Xiaomi.
Eteint les lampes et arrête la sirène en sortie
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$Tab_Lampes					// Tableau des lampes à actionner
//	$Equip_Sirene, $Cmd_SireneOn, $Cmd_SireneOff


// N° des scénarios :

//Variables :
	$max = 15;
	$Periode = 3;
	$VolumeSonore = 3;
	$TypeSirene	= 'Flash+Siren';				// Type de la sirène à lancer : Flash+Siren, Flash, Siren

// Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
mg::setVar('_LampesFolles', 1);			// Variable à annuler pour arrêter le scénario

// Réglage et lancement de la sirène
//mg::setCmd($Equip_Sirene, $TypeSirene);

//mg::setCmd($Cmd_SireneOn);
//Boucle de clignotement des lampes du tableau

$y = 0;
while(mg::getVar('_LampesFolles') == 1 && $y < $max) {
	$i = random_int(0, count($Tab_Lampes)-1);
		mg::GoogleCast('PLAY', random_int(1, 6), $VolumeSonore);
	mg::setCmd($Tab_Lampes[$i], 'On');
	sleep($Periode);
	$i = random_int(0, count($Tab_Lampes)-1);
	mg::setCmd($Tab_Lampes[$i], 'Off');
	sleep($Periode);

	$y++;
}

// Extinction finale des lampes
for ($i = 0; $i < count($Tab_Lampes); $i++) {
	mg::setCmd($Tab_Lampes[$i], 'Off');
// Arrêt de la sirène
//mg::setCmd($Cmd_SireneOff);
}

mg::unsetVar('_LampesFolles');

?>