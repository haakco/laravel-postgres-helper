<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\FuncCall\AddArrayFunctionClosureParamTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php83: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
    ])
    ->withRules([
        AddOverrideAttributeToOverriddenMethodsRector::class,
    ])
    ->withSkip([
        // Skip database migrations as they often need specific syntax
        __DIR__ . '/database/migrations',
        // Rector 2.5.7 + PHPStan 2.x: PHPStanStaticTypeMapper has no
        // handler for ObjectShapeType, rule crashes on array<string, mixed>.
        // Track upstream: https://github.com/rectorphp/rector/issues
        AddArrayFunctionClosureParamTypeRector::class,
    ])
    ->withCache(
        cacheDirectory: __DIR__ . '/.rector_cache'
    );