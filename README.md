# Basic PHP application with authentication enabled at European Commission

Simple PHP application with CAS authentication based on
[ecphp/cas-lib](https://github.com/ecphp/cas-lib) and
[ecphp/ecas](https://github.com/ecphp/ecas).

The application is using the [Slim framework](https://www.slimframework.com/).
The choice of Slim is arbitrary and can be replaced by any other framework. Slim
has been used for its simplicity and its ability to be used as a
micro-framework.

This application is not meant to be used as a production application but as a
simple educational example on how to implement CAS authentication in a bare PHP
application.

## Usage

This application ships with a `flake.nix` file. If you use Nix, you can run the
application "remotely" by doing: `nix run github:ecphp/cas-demo-slim`

If you don't use Nix, you can run the application locally by doing:

```bash
git clone git@github.com:ecphp/cas-demo-slim
cd cas-demo-slim
composer install
composer serve
```
