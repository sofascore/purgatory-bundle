<?= "<?php\n" ?>

namespace <?= $class_data->getNamespace(); ?>;

<?= $class_data->getUseStatements() ?>

/**
 * @implements RouteProviderInterface<<?= $entity ?>>
 */
<?= $class_data->getClassDeclaration() ?> implements RouteProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function provideRoutesFor(Action $action, object $entity, array $entityChangeSet): iterable
    {
        // add with your own logic if needed

        yield new PurgeRoute(
            name: 'app_route', // replace it with your route
            params: [
                'param1' => $entity, // replace it with correct data for route parameters
            ],
        );
    }

    public function supports(Action $action, object $entity): bool
    {
        return $entity instanceof <?= $entity?>
<?php if (!isset($actions)): ?>
;
<?php else: ?>

            && <?= $actions ?>;
<?php endif ?>
    }
}
