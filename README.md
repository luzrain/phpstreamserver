<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://github.com/phpstreamserver/.github/blob/main/assets/phpss_core_light.svg">
    <img alt="PHPStreamServer logo" align="center" width="70%" src="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_core_dark.svg">
  </picture>
</p>

# PHPStreamServer - PHP Application Server
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg)
![Version](https://img.shields.io/github/v/tag/phpstreamserver/phpstreamserver?label=Version&filter=v*.*.*&sort=semver&color=374151)
![Tests Status](https://img.shields.io/github/actions/workflow/status/phpstreamserver/phpstreamserver/tests.yaml?label=Tests&branch=main)

⚠️ This is the monorepo for the main components of [PHPStreamServer](https://phpstreamserver.dev/) ⚠️

**PHPStreamServer** is a high performance event-loop based application server and supervisor for PHP written in PHP.  
PHPStreamServer ships with a number of plugins to extend functionality such as http server, scheduler and logger. See all the plugin packages below.  
With all the power of plugins it can replace traditional setup for running php applications such as nginx, php-fpm, cron, supervisor.

## Documentation

Please read the official documentation: https://phpstreamserver.dev/

## Packages

| Package                                                                      | Downloads                                                                                                                     | Description                                                                                       |
|------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------|
| [**Core**](https://packagist.org/packages/phpstreamserver/core)              | ![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/core?label=Downloads&labelColor=ffffff&color=ffffff)         | The core of PHPStreamServer with a built-in supervisor.                                           |
| [HttpServer](https://packagist.org/packages/phpstreamserver/http-server)     | ![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/http-server?label=Downloads&labelColor=ffffff&color=ffffff)  | Plugin that implements an asynchronous HTTP server.                                               |
| [Scheduler](https://packagist.org/packages/phpstreamserver/scheduler)        | ![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/scheduler?label=Downloads&labelColor=ffffff&color=ffffff)    | Plugin for scheduling tasks. Works similar to cron.                                               |
| [Logger](https://packagist.org/packages/phpstreamserver/logger)              | ![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/logger?label=Downloads&labelColor=ffffff&color=ffffff)       | Plugin that implements a powerful PSR-compatible logger that can be used by workers.              |
| [File Monitor](https://packagist.org/packages/phpstreamserver/file-monitor)  | ![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/file-monitor?label=Downloads&labelColor=ffffff&color=ffffff) | Plugin to monitor files and reload server when files are changed. Useful for development.         |
| [Metrics](https://packagist.org/packages/phpstreamserver/metrics)            | ![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/metrics?label=Downloads&labelColor=ffffff&color=ffffff)      | Plugin that exposes an endpoint with Prometheus metrics. Custom metrics can be sent from workers. |
