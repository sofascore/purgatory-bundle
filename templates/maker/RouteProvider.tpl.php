<?php echo "<?php\n"; ?>

namespace <?php echo $class_data->getNamespace(); ?>;

<?php echo $class_data->getUseStatements(); ?>

/**
 * @implements RouteProviderInterface<<?php echo $entity; ?>>
 */
<?php echo $class_data->getClassDeclaration(); ?> implements RouteProviderInterface
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
        return $entity instanceof <?php echo $entity; ?>
<?php if (!isset($actions)) { ?>
;
<?php } else { ?>

            && <?php echo $actions; ?>;
<?php } ?>
    }
}
