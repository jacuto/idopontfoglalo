<?php
// Per-provider public booking origins are handled by EmbedCors. Avoid the
// framework's wildcard fallback on private account and workspace endpoints.
return ['paths'=>[], 'allowed_methods'=>[], 'allowed_origins'=>[], 'allowed_origins_patterns'=>[], 'allowed_headers'=>[], 'exposed_headers'=>[], 'max_age'=>0, 'supports_credentials'=>false];
