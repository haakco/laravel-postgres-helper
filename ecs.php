<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer;
use PhpCsFixer\Fixer\Import\GlobalNamespaceImportFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Semicolon\MultilineWhitespaceBeforeSemicolonsFixer;
use PhpCsFixer\Fixer\StringNotation\ExplicitStringVariableFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $parameters = $containerConfigurator->parameters();

    $containerConfigurator->import(SetList::SPACES);
    $containerConfigurator->import(SetList::COMMON);
    $containerConfigurator->import(SetList::SYMPLIFY);
    $containerConfigurator->import(SetList::CLEAN_CODE);
    $containerConfigurator->import(SetList::SYMFONY);
    $containerConfigurator->import(SetList::PSR_12);
    $containerConfigurator->import(SetList::PHP_CS_FIXER);
    $containerConfigurator->import(SetList::DOCTRINE_ANNOTATIONS);
    $containerConfigurator->import(SetList::SYMFONY_RISKY);

    // alternative to CLI arguments, easier to maintain and extend
    $parameters->set(Option::PATHS, [
        __DIR__ . '/app',
        __DIR__ . '/tests',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/config',
        __DIR__ . '/ecs.php',
    ]);
    $parameters->set(Option::INDENTATION, 'spaces');
    $parameters->set(Option::LINE_ENDING, "\n");
    $parameters->set(Option::SKIP, [
        '*/Source/*',
        '*/Fixture/*',
        // breaks annotated code - removed on symplify dev-main
        \PhpCsFixer\Fixer\ReturnNotation\ReturnAssignmentFixer::class,
    ]);

    $services->set(ArraySyntaxFixer::class)
        ->call('configure', [
            [
                'syntax' => 'short',
            ],
        ]);

    $services->set(OrderedImportsFixer::class);
    $services->set(TrailingCommaInMultilineFixer::class);
    $services->set(FullyQualifiedStrictTypesFixer::class);
    $services->set(GlobalNamespaceImportFixer::class);
    $services->set(PhpdocAlignFixer::class)
        ->call('configure', [
            [
                'align' => 'left',
            ],
        ]);
    $services->set(ConcatSpaceFixer::class)
        ->call('configure', [
            [
                'spacing' => 'one',
            ],
        ]);
    $services->set(MultilineWhitespaceBeforeSemicolonsFixer::class)
        ->call('configure', [
            [
                'strategy' => 'no_multi_line',
            ],
        ]);
    $services->set(ExplicitStringVariableFixer::class);
};
