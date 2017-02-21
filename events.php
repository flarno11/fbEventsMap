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
    $encoded = urlencode($address);
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=$encoded&region=ch&key=$google_api_key";

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
    
    foreach ($events as &$event) { // iterate by reference (&$event), otherwise $events is not updated with the changed $event
        if (isset($event['place']) && !isset($event['place']['location']) && isset($event['place']['name'])) {
            $event['place']['location'] = addressToGps($event['place']['name'], $google_api_key);
        }
    }
    unset($event); // break the reference with the last element
    
    return $events;
}

$languages = array(
    'de' => array('locale' => 'de_CH.utf8', 'dateFormat' => '%e. %B',),
    'fr' => array('locale' => 'fr_CH.utf8', 'dateFormat' => '%e %B', 'translations' => array('Basel' => 'Bâle')),
    'en' => array('locale' => 'en_US.utf8', 'dateFormat' => '%B %e',),
);
$force = filter_input(INPUT_GET, 'force', FILTER_VALIDATE_BOOLEAN);
if ($force || !file_exists('./events.de.json') || time() - filemtime('./events.de.json') > 5*60) {
    $events = load_fb_events($fb_page, $fb_token, $google_api_key);
    
    foreach ($languages as $l => $lang) {
        $localeSet = setlocale(LC_ALL, $lang['locale']);
        $data = array();
        foreach ($events as $event) {
            $location = isset($event['place']) && isset($event['place']['location']) ? $event['place']['location'] : null;
            if (isset($location) && isset($location['city'])) {
                $city = $location['city'];
                if (isset($lang['translations'][$city])) {
                    $city = $lang['translations'][$city];
                }
                $city .= ' | ';
            } else {
                $city = '';
            }
            $place = isset($event['place']) ? $event['place']['name'] : '';
            $street = isset($location) && isset($location['street']) ? $location['street'] . ' | ' : '';
            
            $startTime = strtotime($event['start_time']);
            
            $startTimeLong = strftime($lang['dateFormat'], $startTime);
            $duration = strftime("%H:%M", $startTime);
            
            if (isset($event['end_time'])) {
                $endTime = strtotime($event['end_time']);
                $duration .= ' - ' . strftime("%H:%M", $endTime);
            }
            
            $data[] = array(
                'id' => $event['id'],
                'localeAsked' => $lang['locale'],
                'localeSet' => $localeSet,
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
