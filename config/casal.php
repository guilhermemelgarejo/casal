<?php

/**
 * Compatibilidade retroativa.
 *
 * O produto passou a usar a configuração `duozen.*` e variáveis `DUOZEN_*`.
 * Mantemos `casal.*` para não quebrar código/config antigos.
 */

return require __DIR__.'/duozen.php';
