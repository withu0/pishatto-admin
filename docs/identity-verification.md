# Guest Identity Verification System

## Overview

The guest identity verification system allows guests to upload their identification documents for admin approval. This ensures compliance and security for the platform.

## Features

### For Guests
- Upload identification documents (driver's license, passport, etc.)
- View verification status (pending, approved, rejected)
- Receive notifications about verification status

### For Admins
- View all pending identity verifications
- Approve or reject verification documents
- Download verification documents for review
- View verification statistics
- Search and filter verification requests

## Database Schema

### Guest Table Fields
- `identity_verification`: File path to uploaded document
- `identity_verification_completed`: Status enum ('pending', 'success', 'failed')

## API Endpoints

### Guest Endpoints
- `POST /api/identity/upload` - Upload identification document
- `GET /api/identity/status` - Get verification status

### Admin Endpoints
- `GET /admin/identity-verifications` - List pending verifications
- `POST /admin/identity-verifications/{guestId}/approve` - Approve verification
- `POST /admin/identity-verifications/{guestId}/reject` - Reject verification
- `GET /admin/identity-verifications/stats` - Get verification statistics

## Admin Interface

### Identity Verification Management Page
- Located at `/admin/identity-verifications`
- Shows all pending verification requests
- Displays guest information and uploaded documents
- Provides approve/reject buttons for each request
- Includes image preview modal for document review
- Supports search and pagination

### Dashboard Integration
- Added verification statistics to admin dashboard
- Quick access card for pending verifications
- Navigation menu item for identity verification management

## Usage Workflow

1. **Guest Uploads Document**
   - Guest uploads identification document via frontend
   - Document is stored in `storage/app/public/identity_verification/`
   - Guest status is set to 'pending'

2. **Admin Reviews Document**
   - Admin accesses `/admin/identity-verifications`
   - Views uploaded document in modal
   - Clicks approve or reject button

3. **Status Update**
   - Guest status is updated to 'success' or 'failed'
   - Guest can view updated status in their profile

## Security Features

- File upload validation (images only, max 5MB)
- Admin-only access to verification management
- Secure file storage in public disk
- Audit trail of approval/rejection actions

## File Storage

Documents are stored in:
- Path: `storage/app/public/identity_verification/`
- Accessible via: `/storage/identity_verification/{filename}`

## Configuration

### File Upload Limits
- Maximum file size: 5MB
- Allowed formats: Images (jpg, png, gif, etc.)
- Storage disk: 'public'

### Status Values
- `pending`: Awaiting admin review
- `success`: Approved by admin
- `failed`: Rejected by admin

## Frontend Integration

The system integrates with the existing guest management interface:

- Guest list shows verification status badges
- Quick approve/reject buttons for pending verifications
- Dedicated verification management page
- Dashboard widgets for verification statistics

## Future Enhancements

- Email notifications for status changes
- Bulk approval/rejection actions
- Document type categorization
- Verification history tracking
- Automated document validation 