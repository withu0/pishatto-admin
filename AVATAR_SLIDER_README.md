# Avatar Slider Component

## Overview

The Avatar Slider component is a new feature that allows displaying multiple avatars for casts in a carousel format. This replaces the single avatar display with an interactive slider that supports navigation, dots indicators, and auto-play functionality.

## Features

- **Multiple Avatar Support**: Displays multiple avatars for a single cast
- **Navigation Controls**: Left/right arrow buttons for manual navigation
- **Dot Indicators**: Shows current position and allows direct navigation
- **Auto-play**: Optional automatic cycling through avatars
- **Hover Pause**: Auto-play pauses when hovering over the slider
- **Responsive Design**: Works on different screen sizes
- **Fallback Support**: Shows fallback text when no avatars are available
- **Avatar Management**: Upload, delete, and manage individual avatars in the edit page

## Avatar Management Features

### Edit Page Integration

The cast edit page now includes comprehensive avatar management:

#### **Avatar Display**
- **Live Preview**: Shows current avatars in the AvatarSlider component
- **Thumbnail Grid**: Displays all avatars in a 3-column grid with delete buttons
- **Real-time Updates**: Changes are reflected immediately in the preview

#### **Upload Functionality**
- **Drag & Drop**: Users can drag files directly onto the upload area
- **File Selection**: Traditional file picker with multiple file support
- **Preview**: Selected files are shown as previews before upload
- **Validation**: Supports image files (JPEG, PNG, JPG, GIF, WebP) up to 20MB
- **Progress Feedback**: Loading states and success/error messages

#### **Delete Functionality**
- **Individual Deletion**: Delete specific avatars with confirmation dialog
- **Storage Cleanup**: Removes files from server storage
- **Database Update**: Automatically updates the cast record
- **Visual Feedback**: Immediate UI updates after deletion

## Component Usage

### Basic Usage

```tsx
import { AvatarSlider } from '@/components/ui/avatar-slider';

<AvatarSlider 
    avatars={avatarUrls}
    fallbackText="AV"
    size="lg"
/>
```

### Advanced Usage

```tsx
<AvatarSlider 
    avatars={cast.avatar_urls}
    fallbackText={cast.nickname?.[0] || "C"}
    size="xl"
    showNavigation={true}
    showDots={true}
    autoPlay={true}
    autoPlayInterval={5000}
/>
```

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `avatars` | `string[]` | `[]` | Array of avatar image URLs |
| `fallbackText` | `string` | `"AV"` | Text to show when no avatar is available |
| `className` | `string` | `undefined` | Additional CSS classes |
| `size` | `"sm" \| "md" \| "lg" \| "xl" \| "2xl" \| "3xl"` | `"lg"` | Size of the avatar |
| `showNavigation` | `boolean` | `true` | Show navigation arrows |
| `showDots` | `boolean` | `true` | Show dot indicators |
| `autoPlay` | `boolean` | `false` | Enable auto-play |
| `autoPlayInterval` | `number` | `3000` | Auto-play interval in milliseconds |

## Size Options

### Circular Avatars
- `sm`: 64x64px (w-16 h-16)
- `md`: 96x96px (w-24 h-24)
- `lg`: 128x128px (w-32 h-32)
- `xl`: 192x192px (w-48 h-48)
- `2xl`: 256x256px (w-64 h-64)
- `3xl`: 320x320px (w-80 h-80)

### Rectangular Avatars
- `sm`: 80x64px (w-20 h-16)
- `md`: 128x96px (w-32 h-24)
- `lg`: 192x144px (w-48 h-36)
- `xl`: 256x192px (w-64 h-48)
- `2xl`: 320x240px (w-80 h-60)
- `3xl`: 384x288px (w-96 h-72)

## Backend Integration

### Cast Model

The Cast model includes methods to handle multiple avatars:

```php
// Get all avatar URLs
$cast->avatar_urls; // Returns array of URLs

// Get first avatar URL
$cast->first_avatar_url; // Returns single URL
```

### Controller Updates

The CastController has been updated to include `avatar_urls` in responses:

```php
public function show(Cast $cast): Response
{
    $cast->load(['likes', 'receivedGifts', 'favoritedBy']);
    $cast->avatar_urls = $cast->avatar_urls; // Add avatar_urls to response
    
    return Inertia::render('admin/casts/show', [
        'cast' => $cast
    ]);
}
```

## Database Storage

Avatars are stored as comma-separated paths in the `avatar` field:

```
"avatars/image1.jpg,avatars/image2.jpg,avatars/image3.jpg"
```

The model automatically parses this into an array of full URLs when accessing `avatar_urls`.

## Implementation Details

### Frontend Changes

1. **New Component**: `resources/js/components/ui/avatar-slider.tsx`
2. **Updated Cast Interface**: Added `avatar_urls?: string[]` property
3. **Updated Show Page**: Replaced single avatar with AvatarSlider component

### Backend Changes

1. **Cast Model**: Uses existing `avatar_urls` accessor
2. **CastController**: Added `avatar_urls` to responses in show, index, and edit methods

## Styling

The component uses Tailwind CSS classes and includes:

- Hover effects for navigation buttons
- Smooth transitions
- Backdrop blur effects
- Responsive design
- Dark mode support

## Accessibility

- Navigation buttons are keyboard accessible
- Screen reader friendly with proper alt text
- Focus indicators for interactive elements
- ARIA labels for better accessibility

## Browser Support

- Modern browsers with CSS Grid and Flexbox support
- Fallback to single avatar display for older browsers
- Progressive enhancement approach 
