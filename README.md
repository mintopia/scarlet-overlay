# Scarlet Overlay

## Introduction

A fairly simple stream overlay for a sailing trip on Scarlet. Probably not much use to anyone else, but who am I to
judge you?

It does have integration with Open Meteo for marine and weather forecasts, and ThingsBoard for GPS tracking data
that is being sent from an ESP32.

## Technology

This project is written in PHP 8.4 using the Laravel 12 framework.vThe application is
served using Laravel Octane and FrankenPHP.

## Development Setup

Why? But OK!

```bash
cp .env.example .env
docker compose run --rm composer install
docker compose run --rm artisan key:generate
docker compose run --rm npm install
docker compose run --rm npm run build
docker compose up -d
```
You will need to edit the `.env` file to have the correct login details, device ID and endpoint for ThingsBoard.


## Contributing

It's an open source project and I'm happy to accept pull requests, although I'm not sure why you would.

## License

The MIT License (MIT)

Copyright (c) 2025 Jessica Smith

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
