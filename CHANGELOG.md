# Changelog

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
