import datetime
import json
import os
import requests

from flask import Flask, jsonify

from config import setup_logging
from lib import TimeFormat

logger = setup_logging()

fb_token = os.getenv("FB_TOKEN")
if not fb_token:
    logger.error("No FB token configured")
    exit(1)

fb_page = os.getenv("FB_PAGE")
if not fb_page:
    logger.error("No FB page configured")
    exit(1)

google_api_key = os.getenv("GOOGLE_APIKEY")
if not google_api_key:
    logger.error("No Google Api Key configured")
    exit(2)


class JSONEncoder(json.JSONEncoder):
    def default(self, o):
        # if isinstance(o, ObjectId):
        #    return str(o)
        # el
        if isinstance(o, datetime.datetime):
            return o.strftime(TimeFormat)
        return json.JSONEncoder.default(self, o)


class InvalidAPIUsage(Exception):
    status_code = 400

    def __init__(self, message, status_code=None, payload=None):
        Exception.__init__(self)
        self.message = message
        if status_code is not None:
            self.status_code = status_code
        self.payload = payload

    def to_dict(self):
        rv = dict(self.payload or ())
        rv['message'] = self.message
        return rv


app = Flask(__name__)


@app.errorhandler(InvalidAPIUsage)
def handle_invalid_usage(error):
    response = jsonify(error.to_dict())
    response.status_code = error.status_code
    return response


@app.route('/')
def index():
    return app.send_static_file('index.html')


@app.route('/config')
def get_config():
    return {'googleApiKey': google_api_key}


@app.route('/events')
def get_events():
    r = requests.get("https://graph.facebook.com/v2.8/" + fb_page + "?fields=events{start_time,place,name}&access_token=" + fb_token)
    if r.status_code != 200:
        return jsonify({'msg': "Could not load facebook events", 'code': r.status_code, 'details': r.text()})

    return jsonify({'msg': None, 'events': r.json()['events']['data']})


app.json_encoder = JSONEncoder

if __name__ == "__main__":
    # port = int(os.getenv("VCAP_APP_PORT", "-1"))
    app.run()
