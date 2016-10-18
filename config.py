import logging.config


def setup_logging():
    logging.config.fileConfig('logging.conf', defaults={})
    logger = logging.getLogger('fbEventsMap')
    logger.setLevel(logging.DEBUG)
    return logger
