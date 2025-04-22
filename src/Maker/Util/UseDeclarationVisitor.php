<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Maker\Util;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
final class UseDeclarationVisitor extends NodeVisitorAbstract
{
    private bool $imported = false;
    private bool $importWithSameNameExists = false;
    private ?string $alias = null;
    private ?int $insertAtLine = null;

    private readonly string $namespace;
    private readonly string $className;

    /**
     * @param class-string $fqcn
     */
    public function __construct(
        public readonly string $fqcn,
    ) {
        $this->namespace = Str::getNamespace($fqcn);
        $this->className = Str::getShortClassName($fqcn);
    }

    public function enterNode(Node $node): void
    {
        if ($this->imported) {
            return;
        }

        if ($node instanceof Node\Stmt\GroupUse && $this->namespace === $node->prefix->name) {
            foreach ($node->uses as $useItem) {
                $this->processUseItem($useItem, checkFqcn: false);
            }
        }

        if ($node instanceof Node\UseItem) {
            $this->processUseItem($node, checkFqcn: true);
        }

        if ($node instanceof Node\Stmt\GroupUse && Node\Stmt\Use_::TYPE_NORMAL === $node->uses[0]->type) {
            $class = "{$node->prefix->name}\\{$node->uses[0]->name->name}";
            if (!Str::areClassesAlphabetical($this->fqcn, $class) && $node->hasAttribute('endLine')) {
                $this->insertAtLine = (int) $node->getAttribute('endLine') + 1;
            }
        }

        if ($node instanceof Node\Stmt\Use_
            && Node\Stmt\Use_::TYPE_NORMAL === $node->type
            && !Str::areClassesAlphabetical($this->fqcn, $node->uses[0]->name->name)
            && $node->hasAttribute('endLine')
        ) {
            $this->insertAtLine = (int) $node->getAttribute('endLine') + 1;
        }
    }

    public function leaveNode(Node $node): void
    {
        if ($this->imported) {
            return;
        }

        if (!$node instanceof Node\Stmt\Namespace_) {
            return;
        }

        $this->alias = $this->className;

        if ($this->importWithSameNameExists) {
            $this->alias = 'Purgatory'.$this->alias;
        }
    }

    public function isImported(): bool
    {
        return $this->imported;
    }

    public function importWithSameNameExists(): bool
    {
        return $this->importWithSameNameExists;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function getInsertAtLine(): ?int
    {
        return $this->insertAtLine;
    }

    public function getFqcn(): string
    {
        return $this->fqcn;
    }

    private function processUseItem(Node\UseItem $node, bool $checkFqcn): void
    {
        if ($this->imported) {
            return;
        }

        $importName = $checkFqcn ? $this->fqcn : $this->className;

        if ($importName !== $node->name->name) {
            if ($this->className === ($node->alias?->name ?? Str::getShortClassName($node->name->name))) {
                $this->importWithSameNameExists = true;
            }

            return;
        }

        $this->imported = true;
        $this->insertAtLine = null;

        /*
         * This covers:
         * use Sofascore\PurgatoryBundle\Attribute\PurgeOn as Alias;
         */
        $this->alias = $node->alias?->name ?? $this->className;
    }
}
