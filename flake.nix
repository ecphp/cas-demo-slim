{
  description = "Example flake for PHP development";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
    nix-php-composer-builder.url = "github:loophp/nix-php-composer-builder";
    systems.url = "github:nix-systems/default";
  };

  outputs = inputs@{ self, flake-parts, systems, ... }: flake-parts.lib.mkFlake { inherit inputs; } {
    systems = import systems;

    perSystem = { config, self', inputs', pkgs, system, lib, ... }:
      let
        php = pkgs.api.buildPhpFromComposer {
          src = inputs.self;
          php = pkgs.php81; # Change to php81, php82, php83 etc.
        };
      in
      {
        _module.args.pkgs = import self.inputs.nixpkgs {
          inherit system;
          overlays = [
            inputs.nix-php-composer-builder.overlays.default
          ];
          config.allowUnfree = true;
        };

        checks = {
          inherit (self'.packages) app;
        };

        apps = {
          default = {
            type = "app";
            program = lib.getExe (pkgs.writeShellApplication {
              name = "app-demo";

              runtimeInputs = [
                php
                php.packages.composer
              ];

              text = ''
                composer serve
              '';
            });
          };
        };

        packages = {
          app = pkgs.api.buildComposerProject {
              pname = "app-demo";
              version = "1.0.0";

              src = ./.;

              php = pkgs.api.buildPhpFromComposer { src = ./.; };

              vendorHash = "sha256-SrE51k3nC5idaDHNxiNM7NIbIERIf8abrCzFEdxOQWA=";
            };
        };

        devShells.default = pkgs.mkShellNoCC {
          name = "php-devshell";

          buildInputs = [
            php
            php.packages.composer
          ];
        };
      };
  };
}
