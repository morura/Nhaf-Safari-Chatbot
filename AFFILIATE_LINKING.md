# Nomad Horizons Safari AI Chatbot v1.0.2 — Affiliate Linking Guide

## New Features

### 1. Auto-linked Destinations in Chatbot Responses

The chatbot now automatically detects and links destination names to Safari.com with your affiliate code.

**Example:**
> **User:** "Which country is best for a safari?"
> 
> **Bot response:** "1. **[Kenya](https://www.safari.com/kenya?a=MR9)** is famous for the **[Maasai Mara](https://www.safari.com/destinations/kenya?a=MR9)** and the Great Migration…"

The linker recognizes:
- **Countries:** Kenya, Tanzania, South Africa, Botswana, Zimbabwe, Namibia, Uganda, Rwanda, Zambia, Malawi, Ethiopia, Madagascar
- **Destinations:** Serengeti, Maasai Mara, Ngorongoro, Kruger, Okavango Delta, Timbavati, Sabi Sands, Kalahari, Victoria Falls

Each is linked with your affiliate ID (`?a=YOUR_AFFILIATE_ID`).

### 2. Affiliate Code Now Uses `?a=` Format

The affiliate parameter was updated from `?aff=` to `?a=` to match Safari.com's expected format.

**Before:** `https://www.safari.com/book?aff=MR9`  
**After:** `https://www.safari.com/book?a=MR9`

---

## Configuration

### 1. Set Your Affiliate Code

Go to **Settings → Safari AI Chatbot → Lead Management**:
- **Affiliate base URL:** `https://www.safari.com/book` (or your custom URL)
- **Affiliate ID:** `MR9` (your code)

Once set, all booking links will include `?a=MR9`.

### 2. Customize Which Destinations Get Linked

Add this to your theme's `functions.php` or a custom plugin to customize destination links:

```php
add_filter( 'nhaf_chatbot_destination_links', function ( $destinations ) {
    // Add custom destination
    $destinations['Mt. Kilimanjaro'] = 'destinations/tanzania';
    
    // Remove a destination from auto-linking
    unset( $destinations['Rwanda'] );
    
    // Override a link's Safari.com path
    $destinations['Serengeti'] = 'experiences/serengeti-safaris';
    
    return $destinations;
} );
```

Keys are what appears in the chatbot response; values are the Safari.com URL path (relative to your affiliate base URL).

### 3. Customize the Linkable Destinations Filter

Advanced: Use `nhaf_chatbot_linkable_destinations` to control which destinations are linked per-request:

```php
add_filter( 'nhaf_chatbot_linkable_destinations', function ( $destinations, $html ) {
    // Link fewer destinations if the response is very short
    if ( strlen( $html ) < 100 ) {
        return array_slice( $destinations, 0, 5 );
    }
    return $destinations;
}, 10, 2 );
```

---

## How It Works

1. **Destination Detection:** When the chatbot generates a response, it mentions places like "Kenya," "Serengeti," etc.
2. **Auto-linking:** The response is processed before being sent to the browser. Destination names are wrapped in `<a>` tags pointing to Safari.com with your affiliate code.
3. **Word boundaries:** Only complete words are matched (e.g., "Serengeti" matches but "Serengetis" does not).
4. **Limit per destination:** A maximum of 5 instances per destination are linked to avoid over-saturation.
5. **Already-linked text:** If a destination is already a link, it isn't double-wrapped.

---

## Booking Links

When a visitor submits a booking form:
1. A lead is created in the database.
2. The system generates an affiliate-tracked booking link.
3. The visitor sees a button labeled **"Book your safari"** pointing to Safari.com with `?a=YOUR_AFFILIATE_ID`.
4. An email is sent to you with the lead details and booking reference.

**Example confirmation message:**
> Thank you, Jane! Your booking reference is **NH-ABC123XYZ**.  
> [Book your safari →](https://www.safari.com/book?a=MR9&ref=NH-ABC123XYZ&destination=Kenya)

---

## Troubleshooting

### Destinations aren't linking

**Check:**
- Is your **Affiliate ID** set under Lead Management?
- Are you using the correct destination name? (e.g., "Kenya" vs "kenya" — case-insensitive but exact word)
- Is the chatbot response containing the destination name?

### Affiliate code missing from booking link

**Check:**
- **Affiliate base URL:** Is it set? (Should be something like `https://www.safari.com/book`)
- **Affiliate ID:** Is it filled in?
- After changing these, refresh your browser cache and test again.

### Custom destinations not linking

If you added custom destinations via the filter but they're not appearing:
- Clear your browser cache
- Re-test the chat in an incognito window
- Check that the filter function name is unique (avoid conflicts with other plugins)

---

## Upgrading from 1.0.1

Simply:
1. Deactivate the old plugin
2. Upload and activate `nhaf-safari-chatbot-1.0.2.zip`
3. No reconfiguration needed — your settings are preserved

Your affiliate ID and base URL will continue to work with the new `?a=` format automatically.
