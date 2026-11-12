# Agent Instructions

Instructions for AI coding agents working on PurgatoryBundle. They complement what `.php-cs-fixer.dist.php`,
`phpstan.dist.neon` and the CI workflows already enforce.

## Branches

- `1.x` and `2.x` are maintained. Fixes target `1.x` and are merged into `2.x`, features target `2.x`.
- Work on a new branch cut from the target branch. Don't commit to `1.x` or `2.x` directly, except for the changelog
  commit of a release.

## Checks

- Run `composer run-checks`, or `vendor/bin/php-cs-fixer fix`, `vendor/bin/phpunit` and `vendor/bin/phpstan analyse`
  separately. All three run in CI.
- CI tests several PHP and Symfony versions. Code and doc snippets must work on the lowest PHP version in
  `composer.json`, so no syntax from later versions, such as an enum's `->value` inside an attribute argument on 8.1.
- PHPStan runs at level 10 on `src`. When a runtime check guards a type the docblock already promises, keep the strict
  type and add the "always true" or "always false" error to `phpstan-baseline.neon`, like the check in `EnumValues`.
  Don't loosen the type to silence it.

## Comments

- Only add a comment when it says something the code doesn't: a contract, a non-obvious reason or a trap. Don't add
  descriptions that repeat a parameter or method name, comments that explain a line that reads fine on its own, or
  test docblocks that repeat the test name. The reasoning behind a change belongs in the commit message.
- Parameters of attributes that applications write, such as `#[PurgeOn]`, may explain how to use them.
- Don't use semicolons in comments, docblocks or any other prose. Use a period or a comma instead.

## Tests

- Cover every change with tests, and check that a new test fails without the change.
- Put a new test right after the existing test it relates to, for example the same feature or the same error path.
  Don't put it between two tests that belong together, and don't move existing tests. When nothing is related, add it
  at the end of a group or of the class.
- A data provider goes right after the test that uses it.
- Don't add tests that only check a default value, that a constructor keeps its arguments, or that a value passes
  through unchanged.
- Before adding a test class, check whether the class is already covered through another test (`#[CoversClass]`),
  and extend that test instead.

## Docs

- Docs and the changelog wrap at 120 characters.
- Document a feature where its users look. For example, what only matters to custom purgers goes in
  `docs/custom-purgers.md`, not the README.

## Changelog

- Follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Each PR adds its entries under `## [Unreleased]`,
  in `Added`, `Changed` or `Fixed`.
- Say what the change does for users, not how it is implemented.
- Use `- <change> by @<author> in https://github.com/sofascore/purgatory-bundle/pull/<number>`. Wrap at 120
  characters and indent the continuation by two spaces. When `by @<author>` still fits, move only the link to the next
  line.
- Don't rewrite the entry of another PR, and don't add breaking change entries for things that were never released.

## Commits

- Make one commit per problem. Write the subject in the imperative, put code in backticks, and explain why in the
  body.
- Add a new commit for each new change. Fold it into an earlier commit only when asked to.
- Don't add `Co-Authored-By` trailers.

## Releases

Maintainers push branches and tags and create the GitHub release, using the changelog section as the release notes.

- A release with only `Fixed` entries is a patch release. `Added` entries make it a minor release.
- Patch releases usually go out on both branches together:
    1. On `1.x`, rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD` and the link
       `[Unreleased]: .../compare/vPREV...1.x` to `[X.Y.Z]: .../compare/vPREV...vX.Y.Z`. Commit this as
       "Update Changelog".
    2. Merge `1.x` into `2.x`. The changelog conflicts. Resolve it so that the 2.x release section is at the top, a copy
       of the 1.x section sits above the previous 1.x section, and the links follow the same order.
- For a release on `2.x` only, rename its `## [Unreleased]` section and link directly on `2.x` and commit that as
  "Update Changelog".
- Tags are lightweight. A `v1` tag points at the 1.x "Update Changelog" commit. A `v2` tag points at the merge of
  `1.x` into `2.x`, or at the 2.x "Update Changelog" commit for a release on `2.x` only.

## Reviews

- Verify a finding before reporting it: read the code, run it, or reproduce it with a small script. Say what wasn't
  verified, and correct an earlier claim as soon as it turns out to be wrong.
- Tell problems the change introduces apart from ones that already existed.
- Rank findings by how likely they are to matter, and say when something is an edge case.
- In a follow-up review, leave out findings that were already discussed and decided.
