LOGGING_CONFIG = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'airflow': {
            'format': '[%(asctime)s] %(levelname)s - %(message)s',
        },
    },
    'handlers': {
        'console': {
            'class': 'logging.StreamHandler',
            'formatter': 'airflow',
            'stream': 'ext://sys.stdout',
        },
    },
    'root': {
        'handlers': ['console'],
        'level': 'INFO',
    },
}