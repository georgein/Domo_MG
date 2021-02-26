<?php header('Content-Type: text/XML; charset=windows-1252'); ?>
<?php 
//author : erwan2212@gmail.com - http://labalec.fr/erwan

$id=$_GET['id'];
//http://sourceforge.net/projects/simplehtmldom/files/
include 'simple_html_dom.php';

function compute_rh($temp,$dew) 
{ 
$a=17.271;$b=237.7;
$up=exp(($a*$dew) / ($b+$dew));
$down=exp(($a*$temp) / ($b+$temp));
return 100*($up / $down);
} 


$html_m = file_get_html('http://cunimb.net/decodemet.php?station='.$id);

       $text=$html_m;
       $pos = strpos($text,'>'.$id ); 
       if ($pos !== false) {
       $metar = substr($text, $pos+1); //we strip everything before METAR:
       $pos = strpos($metar,'<' ); 
       $metar = substr($metar, 0,$pos); //we strip everything after <br>
       //echo "->".$metar . '<br>';
       }
	   

$ar = explode(" ", $metar);  
echo ('<?xml version="1.0" encoding="ISO-8859-1"?>'); 
echo ('<metar>'); 
//echo('<test>ok</test>');
for ($i = 0; $i <= count($ar)-1; $i++) {
    //echo $ar[$i].".";

    if (strpos($ar[$i],'Z') !== false && strlen($ar[$i])==7) {
    echo ('<timestamp>'); 
    echo ($ar[$i]);
    echo ('</timestamp>'); 
    }
    
    if (strpos($ar[$i],'KT' ) !== false) {
    $vitesse=substr($ar[$i], 3,2);
    $vitesse=$vitesse / 1.852; //knot to km/h
	$dir=substr($ar[$i], 0,3);
	echo ('<wind>'); echo ($dir."° ".number_format($vitesse,2)." km/h"); echo ('</wind>'); 
	echo ('<wind_dir>'); echo ($dir); echo ('</wind_dir>'); 
	echo ('<wind_speed_kmh>'); echo (number_format($vitesse,2)); echo ('</wind_speed_kmh>'); 
    }
    
    if (strpos($ar[$i],'FEW' ) !== false) echo '<cloud>quelques nuages a '.substr($ar[$i], 3,3).'00 pieds</cloud>'; 
    if (strpos($ar[$i],'SCT' ) !== false) echo '<cloud>épars a '.substr($ar[$i], 3,3).'00 pieds</cloud>'; 
    if (strpos($ar[$i],'BKN' ) !== false) echo '<cloud>fragmentés a '.substr($ar[$i], 3,3).'00 pieds</cloud>'; 
    if (strpos($ar[$i],'OVC' ) !== false) echo '<cloud>couverts a '.substr($ar[$i], 3,3).'00 pieds</cloud>'; 
    if (strpos($ar[$i],'NSC' ) !== false) echo '<cloud>aucun a ' .substr($ar[$i], 3,3).'00 pieds</cloud>'; 
    
    //if (strpos($ar[$i],'/' ) !== false && strlen($ar[$i])==5) {
    if (preg_match("/^([0-9][0-9]\/[0-9][0-9])$/", $ar[$i])) {
	$temperature=$ar[$i];
	$temp=substr($temperature, 0,2);
    echo ('<temperature>');
    echo $temp;  
    echo ('</temperature>'); 
    echo ('<dewpoint>'); 
	$dew=substr($temperature, 3,2);	
    echo $dew;  
    echo ('</dewpoint>');
	$rh= compute_rh($temp,$dew);
	echo ('<rh>'); 	
	echo number_format($rh, 0, '.', '');
	echo ('</rh>'); 
    }
	
	if ($temperature=='') {
	if (preg_match("/^(M[0-9][0-9]\/M[0-9][0-9])$/", $ar[$i])) {
	$temperature=$ar[$i];
	$temperature = str_replace("M", "", $temperature);
	$temperature = str_replace("-", "", $temperature);
	$temp=substr($temperature, 0,2);
    echo ('<temperature>'); 
    echo "-".$temp;  
    echo ('</temperature>');
	$dew=substr($temperature, 3,2);	
    echo ('<dewpoint>'); 
    echo "-".$dew; 
    echo ('</dewpoint>');   
	$rh= compute_rh($temp,$dew);
	echo ('<rh>'); 	
	echo number_format($rh, 0, '.', '');
	echo ('</rh>');	
	}
	}
	
	if ($temperature=='') {
	if (preg_match("/^([0-9][0-9]\/M[0-9][0-9])$/", $ar[$i])) {
	$temperature=$ar[$i];
	$temperature = str_replace("M", "", $temperature);
	$temperature = str_replace("-", "", $temperature);
	$temp=substr($temperature, 0,2);
    echo ('<temperature>'); 
    echo $temp;  //humidite relative
    echo ('</temperature>'); 
	$dew=substr($temperature, 3,2);
    echo ('<dewpoint>'); 
    echo "-".$dew;  //humidite relative
    echo ('</dewpoint>');  
	$rh= compute_rh($temp,$dew);
	echo ('<rh>'); 	
	echo number_format($rh, 0, '.', '');
	echo ('</rh>');		
	}
	}
    
    //if (strpos($ar[$i],'Q' ) !== false  && strlen($ar[$i])==5) {
    if (preg_match("/^(Q[0-9][0-9][0-9][0-9])$/", $ar[$i])) {
    echo ('<pressure>'); 
    echo substr($ar[$i], 1,4);   
    echo ('</pressure>'); 
    }
    
    //group () of 4 digits with end of string ($)
    if (preg_match("/^([0-9][0-9][0-9][0-9])$/", $ar[$i])) {
    echo '<visibility>'.$ar[$i].'</visibility>'; 
    }
    
    if (strpos($ar[$i],'CAVOK' ) !== false) echo '<visibility>Visibilite: de 10 Km ou plus</visibility>'; 

    if (strpos($ar[$i],'NOSIG' ) !== false) echo '<trend>aucun changement significatif dans les deux heures a venir</trend>'; 
    if (strpos($ar[$i],'BECMG' ) !== false) echo '<trend>changements prévus, avec les heures de début et de fin</trend>'; 
    if (strpos($ar[$i],'GRADU' ) !== false) echo '<trend>changements prévus qui va arriver progressivement</trend>'; 
    if (strpos($ar[$i],'RAPID' ) !== false) echo '<trend>changements prévus rapidement (avant une demi-heure en moyenne)</trend>'; 
    if (strpos($ar[$i],'TEMPO' ) !== false) echo '<trend>fluctuations temporaires dans un bloc de 1 a 4 heures</trend>'; 
    if (strpos($ar[$i],'INTER' ) !== false) echo '<trend>changements fréquents mais brefs</trend>';     
    if (strpos($ar[$i],'TEND' ) !== false) echo '<trend>indéterminée</trend>';  
    
    if (strpos($ar[$i],'RA' ) !== false) echo '<weather>Pluie</weather>'; 
    if (strpos($ar[$i],'BR' ) !== false) echo '<weather>Brume</weather>'; 
    if (strpos($ar[$i],'SN' ) !== false) echo '<weather>Neige</weather>'; 
    if (strpos($ar[$i],'GR' ) !== false) echo '<weather>Grêle</weather>'; 
    if (strpos($ar[$i],'DZ' ) !== false) echo '<weather>Bruine</weather>'; 
    if (strpos($ar[$i],'PL' ) !== false) echo '<weather>Granules de glace</weather>'; 
    if (strpos($ar[$i],'GS' ) !== false) echo '<weather>Neige roulée (ou grésil)</weather>';     
    if (strpos($ar[$i],'SG' ) !== false) echo '<weather>Neige en grains</weather>';  
    if (strpos($ar[$i],'IC' ) !== false) echo '<weather>Cristaux de glace</weather>';  
    if (strpos($ar[$i],'UP' ) !== false) echo '<weather>Précipitation inconnue</weather>';  
    
  
}
echo ('</metar>'); 

  
?>
