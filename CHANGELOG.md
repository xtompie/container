# Changelog

## 1.13.0 2024-09-23

- phpstan, cs fix

## 1.12.0 2024-09-23

- Changed custom argument resolved to `callable(ReflectionParameter):mixed`

## 1.11.0 2024-09-22

- Added optional `$arg` argument to `call` and `callArgs` to allow custom resolvers for resolving arguments.

## 1.10.0 2024-09-19

- Add callArgs to resolve callable args.

## 1.9.0 2024-09-19

- Extended the `call` method to allow passing custom values (`values`) that override container-resolved dependencies.

## 1.8.1 2024-09-14

- Added type template annotations (`@template`) and parameter comments to the `get()` and `resolve()` methods.

## 1.8.0 2024-09-14

- Services can now implement the `Provider` interface to self-provide instances without needing manual provider registration in the container.

## 1.7.0 2024-09-13

- Remove `callbackArgs`

## 1.6.0 2024-09-13

- Added `callbackArgs` method to return resolved arguments

## 1.5.0 2024-09-11

- The provider is now executed for concrete classes, previously it was for abstract classes.

## 1.4.0 2024-09-10

- Added `call` method to allow invoking closures, static methods, and instance methods with automatic dependency injection.

## 1.3.0 2024-09-09

- `resolve` method always returns a new instance, ensuring transient behavior and avoiding shared state.

## 1.2.0 2024-09-09

- Added `resolve` method, allowing manual injection of specific values into class constructors.

## 1.1.0 2024-09-08

- Provider returns object.
- Removed not used Enhancer.

## 1.0.0 2024-09-08

- Init.