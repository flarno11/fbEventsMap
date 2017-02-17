var style = [   {     "featureType": "administrative.country",     "stylers": [       {         "color": "#2fac66"       }     ]   },   {     "featureType": "administrative.country",     "elementType": "geometry.stroke",     "stylers": [       {         "color": "#2fac66"       },       {         "weight": 2       }     ]   },   {     "featureType": "administrative.country",     "elementType": "labels.text",     "stylers": [       {         "weight": 0.5       }     ]   },   {     "featureType": "administrative.land_parcel",     "elementType": "labels",     "stylers": [       {         "visibility": "off"       }     ]   },   {     "featureType": "landscape",     "stylers": [       {         "color": "#f3f3f3"       }     ]   },   {     "featureType": "poi",     "elementType": "labels.text",     "stylers": [       {         "visibility": "off"       }     ]   },   {     "featureType": "poi.business",     "stylers": [       {         "visibility": "off"       }     ]   },   {     "featureType": "poi.park",     "elementType": "labels.text",     "stylers": [       {         "visibility": "off"       }     ]   },   {     "featureType": "road",     "stylers": [       {         "visibility": "off"       }     ]   },   {     "featureType": "road.local",     "elementType": "labels",     "stylers": [       {         "visibility": "off"       }     ]   } ];

google.load("maps", "3", {other_params: 'key='+config.googleApiKey, callback: function(){
    var map = new google.maps.Map(document.getElementById('map'), {
      minZoom: 6,
      zoom: 8,
      center: {lat: 47.04681, lng: 8.3165},
      styles: style,
      scrollwheel: false
    });
    
    var l = location.search.split('l=')[1];
    if (l === undefined) {
        l = 'de';
    }
    $.getJSON("./events." + l + ".json", function(data) {
        if (data['msg']) {
            console.log(data);
            return;
        }

        var i;
        var eventsGroupBySameLocation = {};
        for (i = 0; i < data['events'].length; ++i) {
            var event = data['events'][i];
            if (event['location']) {
                console.log(event);
                var key = event['location']['latitude'].toFixed(6) + ' ' + event['location']['longitude'].toFixed(6);
                if (!(key in eventsGroupBySameLocation)) {
                    eventsGroupBySameLocation[key] = [];
                }
                eventsGroupBySameLocation[key].push(event);
            }
        }

        console.log(eventsGroupBySameLocation);
        var bounds = new google.maps.LatLngBounds();
        for (var key in eventsGroupBySameLocation) {
            var events = eventsGroupBySameLocation[key];
            var position = new google.maps.LatLng(events[0]['location']['latitude'], events[0]['location']['longitude']);
            var marker = new google.maps.Marker({
                position: position, map: map,
                title: event['name'], icon: './marker.png'
            });
            bounds.extend(marker.getPosition());

            var j = 0;
            var content = '';
            for (j = 0; j < events.length; j++) {
                var event = events[j];
                content += '<div>'
                 + '<a href="https://www.facebook.com/events/' + event['id'] + '">' + event['name'] + '</a><br />'
                 + event['place'] + '<br />'
                 + event['startTime']
                 + '</div><hr />';
            }

            var infowindow = new google.maps.InfoWindow();

            google.maps.event.addListener(marker, 'click', (function(marker, content, infowindow) {
                return function() {
                    infowindow.setContent(content);
                    infowindow.open(map,marker);
                };
            })(marker,content,infowindow));
            
            google.maps.event.addListener(map, 'zoom_changed', function() {
                console.log('zoom=' + map.getZoom());
            });
        }
        if (i > 0) {
            // Don't zoom in too far on only one marker, setting zoom directly after fitBounds() does not yet work
            if (bounds.getNorthEast().equals(bounds.getSouthWest())) {
               var extendPoint1 = new google.maps.LatLng(bounds.getNorthEast().lat() + 0.05, bounds.getNorthEast().lng() + 0.05);
               var extendPoint2 = new google.maps.LatLng(bounds.getNorthEast().lat() - 0.05, bounds.getNorthEast().lng() - 0.05);
               bounds.extend(extendPoint1);
               bounds.extend(extendPoint2);
            }
            
            map.fitBounds(bounds);
        }
    });
}});
