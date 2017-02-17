<?php
require_once 'config.php';

function filterUP($event)
{
    $s1 = strtotime($event['start_time']);
    if ($s1 < (time() - 2 * 24 * 60 * 60)) {
        return false;
    }
    return strpos($event['name'], "Get UP") !== false || strpos($event['name'], "GetUP") !== false;
}
function sortUP($event1, $event2)
{
    $s1 = strtotime($event1['start_time']);
    $s2 = strtotime($event2['start_time']);
    if ($s1 == $s2) {
        return 0;
    }
    return ($s1 > $s2) ? +1 : -1;
}
function addressToGps($address, $google_api_key) {
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=$address&region=ch&key=$google_api_key";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $data = curl_exec($ch);
    curl_close($ch);

    $r = json_decode($data, true);
    if (isset($r['results']) && isset($r['results'][0]['geometry']['location'])) {
        return array(
            'latitude' => $r['results'][0]['geometry']['location']['lat'],
            'longitude' => $r['results'][0]['geometry']['location']['lng'],
        );
    } else {
        return null;
    }
}
function load_fb_events($fb_page, $fb_token, $google_api_key) {
    $url = "https://graph.facebook.com/v2.8/" . $fb_page . "?fields=events{start_time,end_time,place,name}&access_token=" . $fb_token;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $data = curl_exec($ch);
    curl_close($ch);

    $results = json_decode($data, true);
    $events = array_values(array_filter($results['events']['data'], "filterUP"));
    usort($events, "sortUP");
    
    foreach ($events as $event) {
        if (isset($event['place']) && !isset($event['place']['location']) && isset($event['place']['name'])) {
            $event['place']['location'] = addressToGps($event['place']['name'], $google_api_key);
        }
    }
    
    return $events;
}

$languages = array('de' => 'de_CH.utf8', 'fr' => 'fr_CH.utf8', 'en' => 'en_US.utf8');

if (!file_exists('./events.de.json') || time() - filemtime('./events.de.json') > 5*60) {
    $events = load_fb_events($fb_page, $fb_token, $google_api_key);
    
    foreach ($languages as $l => $locale1) {
        $locale2 = setlocale(LC_ALL, $locale1);
        $data = array();
        foreach ($events as $event) {
            $location = isset($event['place']) && isset($event['place']['location']) ? $event['place']['location'] : null;
            $city = isset($location) && isset($location['city']) ? $location['city'] . ' | ' : '';
            $place = isset($event['place']) ? $event['place']['name'] : '';
            $street = isset($location) && isset($location['street']) ? $location['street'] . ' | ' : '';
            
            $startTime = strtotime($event['start_time']);
            
            $startTimeLong = strftime("%e. %B", $startTime);
            $duration = strftime("%H:%M", $startTime);
            
            if (isset($event['end_time'])) {
                $endTime = strtotime($event['end_time']);
                $duration .= ' - ' . strftime("%H:%M", $endTime);
            }
            
            $data[] = array(
                'id' => $event['id'],
                'locale1' => $locale1,
                'locale2' => $locale2,
                'title' => $startTimeLong . ' | ' . $city . $event['name'],
                'subTitle' => (isset($place) ? $place . ' | ' : '') . $street . $duration,
                'startTime' => $startTimeLong,
                'name' => $event['name'],
                'place' => $place,
                'location' => $location,
            );
        }
        
        file_put_contents('./events.' . $l . '.json', json_encode(array('events' => $data)));
    }
}

$l = filter_input(INPUT_GET, 'l', FILTER_SANITIZE_STRING);
if (!array_key_exists($l, $languages)) {
    $l = 'de';
}
header('Content-Type: application/javascript');
$data = json_decode(file_get_contents('./events.' . $l . '.json'), true);
echo 'var events=' . json_encode($data['events']) . ';' ;
