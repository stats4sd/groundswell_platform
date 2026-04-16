<?php

/** @phpstan-ignore-next-line */
arch()->expect(['dd', 'ddd'])->not()->toBeUsed();

// ray and dump are covered by the php() preset

/** @phpstan-ignore-next-line */
arch()->preset()->php();

/** @phpstan-ignore-next-line */
arch()->preset()->security();
