# Runtime dependencies

Action Scheduler is bundled at the exact version recorded in composer.lock (GPL-3.0-or-later). Its source and license are included under vendor/woocommerce/action-scheduler. It is not initialized or scheduled during the foundation milestone; its loader will be connected when the queue infrastructure is implemented.

No external service is enabled by UOP Core. Composer and npm contact registries only during development/build.
