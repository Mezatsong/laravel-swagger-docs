<!-- HTML for static distribution bundle build -->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>{{ config('swagger.title') }}</title>
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700|Source+Code+Pro:300,600|Titillium+Web:400,600,700" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/3.36.0/swagger-ui.min.css" integrity="sha512-c9Fmh2rJWb5kaFhvVlPcBLrFdzrVXkdTofQAozw6MfOsC3DwN6pHrpqk9gm8qwJh9wURiK1Hv57/GxmzJzew8g==" crossorigin="anonymous" />
        <style>
            html
            {
                box-sizing: border-box;
                overflow: -moz-scrollbars-vertical;
                overflow-y: scroll;
            }

            *,
            *:before,
            *:after
            {
                box-sizing: inherit;
            }

            body
            {
                margin:0;
                background: #fafafa;
            }
        </style>
    </head>

    <body>
        <div id="swagger-ui"></div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/3.36.0/swagger-ui-bundle.js" integrity="sha512-nsOxDu2mkW1RaAERVAb/cXBM8mykI74y3tJ5SjjEfGHVfyiFXWwdUAHuCy2XZxMpZcPrCKOoPmNT8Fk2eF+i5g==" crossorigin="anonymous" charset="UTF-8"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/3.36.0/swagger-ui-standalone-preset.js" integrity="sha512-/puZAOZVY7sKGhiOx76byRJ70RRY+k6aswiFIHNVLlj53WOg+grt1N4HIYw1OwvEi/RN8XV2NM40xS+tY2zR7g==" crossorigin="anonymous" charset="UTF-8"></script>

        <script type="text/javascript">
            window.onload = function() {
                const ui = SwaggerUIBundle({
                    url: "{!! $urlToDocs !!}",
                    dom_id: '#swagger-ui',
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset
                    ],
                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],
                    layout: "StandaloneLayout",
                    filter: true,
                    deepLinking: true,
                    displayRequestDuration: true,
                    showExtensions: true,
                    showCommonExtensions: true,
                    queryConfigEnabled: true,
                    persistAuthorization: true,
                    // "list", "full", "none"
                    docExpansion: "{{ request()->get('expansion', 'list') }}"
                });

                window.ui = ui;
            }
        </script>
    </body>
</html>
