# Public form request authority

Deferred forms use Livewire's global update endpoint, which does not retain a
site's path mount. Resolve the current request's scheme, host and port together
with the originating page path through `SiteResolver`, then check that the site
still exists and is enabled. The encrypted site/form references only confirm
that this resolved site owns the snapshot and form.

The initial parent is only a placeholder. It records a consistency reference
from the frontend context already resolved for that page, without querying
during public Blade rendering. It grants no form access until the deferred
load passes the request authority checks.

`FormRequestContext` reads `memo.path` only from Livewire's `snapshot-verified`
event. Livewire verifies its HMAC before this event; the checksum covers the
data and memo except `memo.children`. Do not replace this listener with
`Livewire::originalPath()`: that helper reads the first request component before
verification and cannot identify the component currently being processed in a
bundle. Referer, forwarded headers outside Laravel's trusted-proxy handling,
update properties and method parameters are not authority.

The form adds the issuing scheme/host/port as `memo.origin`, inside that same
checksum. This extra binding is necessary: Core can legitimately resolve a root
wildcard to its original site on another site's host outside that site's path
mount. Re-resolution alone therefore does not prevent whole-snapshot replay.
Cross-origin snapshots are refused even when both origins are aliases of the
same site. A form freshly issued on either alias works normally there.

Livewire's path omits the application's public base path. `memo.basePath` binds
that prefix into the checksum as well, including a trusted proxy's forwarded
prefix. Reconstructing the external page path therefore uses both verified
fields, never the update request's prefix or an unsigned page URL. Proxy tests
configure Laravel's `TrustProxies` middleware: setting Symfony's static trust
list alone is ineffective because the middleware resets it on each request.

Children mounted during deferred loading inherit the verified parent context.
Every component in an update bundle replaces that context with its own verified
memo. Resolution results, including null, live only in request attributes keyed
by current page URL and issuing origin. Component form lookup also remembers
null for that component instance; repeated rendering helpers do not repeat the
failed lookup. Neither cache persists into the next HTTP request.

Root domains, registered aliases/www/staging hosts, explicit ports, trusted
proxies and shared-host path mounts use the normal frontend resolver. Unknown
hosts fail closed whether default-site redirection is enabled or disabled:
that frontend option redirects a page request rather than granting access on
the unknown origin. Sites have enabled/soft-deleted state; there is no separate
site publication flag. Active form checks remain in the form resolver.

The origin and path are public routing information, not visitor secrets.
Whole-page HTML cache hits and cached render-hook fragments work for another
visitor on the issuing origin. Snapshots issued before the origin binding was
introduced fail closed; deployment must regenerate any cached HTML containing
those old snapshots. No existing cache or visitor session is flushed by this
package. Stored submission URL metadata uses the validated page path on the
current origin, excluding unsigned query parameters and the Livewire endpoint.

`FoundationFormRequestSecurityTest` exercises all four HTTP entry points,
topology changes, per-request rejection queries, mixed-origin bundles,
trusted proxies, checksum tampering and actual HTML-cache hits. Corrupt
checksums are rejected by Livewire with its production 419 response before
form work; valid snapshots with rejected routing context use the translated
unavailable fallback and cannot submit.
