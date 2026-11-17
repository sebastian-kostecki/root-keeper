# PHP

- Pint (preset `laravel`, `pint.json`) fixes formatting and braces and Larastan (level 7, `phpstan.neon`) reports missing type hints: run them (see Conventions) instead of restating style rules.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Enum keys are TitleCase and string-backed, as in `app/Enums/LocalityKind.php` (`case City = 'city'`).
- Prefer PHPDoc blocks (with array shape types) over inline comments. An inline comment is allowed in two cases only: a Larastan annotation (`@var`, `@phpstan-ignore-next-line` with the reason) or a migration stating why `down()` cannot restore the data.
