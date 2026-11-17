# Do Things the Laravel Way

- Create files with `vendor/bin/sail artisan make:...` and pass `--no-interaction` to every Artisan command; `make:test` always gets `--pest`. Check other flags with `vendor/bin/sail artisan <command> --help` before using them.
