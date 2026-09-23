---
paths:
  - 'bootstrap/**'
---

# Bootstrap

## Trust Dokploy TLS termination
Production app has no public container port and receives HTTPS traffic through Dokploy Traefik over HTTP. Keep trusted proxy handling enabled in bootstrap/app.php so redirects, named routes, and asset URLs retain HTTPS. Verify with an X-Forwarded-Proto feature test and a production redirect check.
