google.load("maps", "3", {other_params: {}, key: config.googleApiKey, callback: function(){
    var map = new google.maps.Map(document.getElementById('map'), {
      //zoom: 4,
      //center: {lat: -25.363, lng: 131.044}
    });

    $.getJSON( "/events", function(data) {
        if (data['msg']) {
            console.log(data);
            return;
        }

        var bounds = new google.maps.LatLngBounds();
        var i;
        for (i = 0; i < data['events'].length; ++i) {
            var event = data['events'][i];
            //console.log(event);
            if (event['place'] && event['place']['location']) {
                var position = new google.maps.LatLng(event['place']['location']['latitude'], event['place']['location']['longitude']);
                var marker = new google.maps.Marker({position: position, map: map, title: event['name']});
                bounds.extend(marker.getPosition());

                var content = '<div><a href="https://www.facebook.com/events/' + event['id'] + '">' + event['name'] + '</a><br />' + event['start_time'] + '</div>';
                var infowindow = new google.maps.InfoWindow();

                google.maps.event.addListener(marker, 'click', (function(marker, content, infowindow) {
                    return function() {
                        infowindow.setContent(content);
                        infowindow.open(map,marker);
                    };
                })(marker,content,infowindow));
            }
        }
        map.fitBounds(bounds);
    });



    var markers = [];//some array
    var bounds = new google.maps.LatLngBounds();
    for (var i = 0; i < markers.length; i++) {
     bounds.extend(markers[i].getPosition());
    }
    map.fitBounds(bounds);

}});
