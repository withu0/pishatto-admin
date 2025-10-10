# Realtime Logging Documentation

## Overview

This document describes the comprehensive logging system implemented for realtime features in the Pishatto application. The logging system helps debug production issues with Reverb/WebSocket connections.

## Log Files

### Main Log Files
- `storage/logs/laravel.log` - General application logs
- `storage/logs/realtime.log` - Dedicated realtime feature logs

### Log Rotation
- Daily rotation with 14 days retention (configurable via `LOG_DAILY_DAYS`)

## Logging Components

### 1. BroadcastServiceProvider
Logs broadcast service initialization and configuration.

**Log Events:**
- Service initialization with Reverb configuration
- Route registration
- Successful initialization

### 2. Channel Authorization
Logs all channel authorization attempts and results.

**Channels Logged:**
- `chat.{chatId}` - Individual chat channels
- `group.{groupId}` - Group chat channels  
- `user.{userId}` - User-specific channels
- `guest.{guestId}` - Guest-specific channels
- `cast.{castId}` - Cast-specific channels

**Log Data:**
- User type and ID
- Channel name
- Authorization result
- Timestamp

### 3. Event Broadcasting
Comprehensive logging for all broadcast events.

**Events Logged:**
- `MessageSent` - Individual message broadcasts
- `GroupMessageSent` - Group message broadcasts
- `ChatCreated` - Chat creation events
- `ChatListUpdated` - Chat list updates
- `MessagesRead` - Message read status updates

**Log Data:**
- Event type and name
- Message/Chat details
- Channel information
- Broadcast data size
- Timestamp

### 4. ChatController Operations
Logs all realtime chat operations.

**Operations Logged:**
- Message creation and broadcasting
- Group message creation and broadcasting
- Chat creation and broadcasting
- Chat list updates

### 5. RealtimeLogService
Centralized logging service for realtime features.

**Methods:**
- `logConnection()` - Connection events
- `logBroadcast()` - Broadcasting events
- `logChannelAuth()` - Channel authorization
- `logMessage()` - Message events
- `logChat()` - Chat events
- `logError()` - Error events
- `logConfig()` - Configuration logging

## Usage

### Testing Logging
```bash
# Test the logging system
php artisan realtime:test-logging

# Test with specific message
php artisan realtime:test-logging --message-id=123

# Test with specific chat
php artisan realtime:test-logging --chat-id=456
```

### Viewing Logs
```bash
# View realtime logs
tail -f storage/logs/realtime.log

# View general logs
tail -f storage/logs/laravel.log

# Search for specific events
grep "MessageSent" storage/logs/realtime.log
grep "Channel Auth" storage/logs/realtime.log
```

## Log Levels

- **INFO** - Normal operations, successful events
- **WARNING** - Authorization failures, non-critical issues
- **ERROR** - Critical errors, connection failures

## Production Debugging

### Common Issues to Look For

1. **Connection Issues**
   - Look for "Realtime Connection" logs
   - Check Reverb configuration logs
   - Verify host/port/scheme settings

2. **Authorization Failures**
   - Look for "Channel Auth" warnings
   - Check user type and ID mismatches
   - Verify channel authorization logic

3. **Broadcasting Failures**
   - Look for "Realtime Broadcast" logs
   - Check event creation and channel determination
   - Verify data size and content

4. **Message Issues**
   - Look for "Realtime Message" logs
   - Check message creation and broadcasting
   - Verify recipient type handling

### Environment Variables

Add these to your `.env` file for enhanced logging:

```env
# Reverb Configuration
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_APP_ID=your_app_id
REVERB_HOST=your_reverb_host
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_DEBUG=true
REVERB_LOG_LEVEL=debug

# Logging Configuration
LOG_LEVEL=debug
LOG_DAILY_DAYS=14
```

## Monitoring

### Key Metrics to Monitor
- Connection success/failure rates
- Authorization success/failure rates
- Message broadcast success rates
- Error frequency and types

### Alerts to Set Up
- High error rates in realtime logs
- Authorization failure spikes
- Connection timeout issues
- Broadcast failure patterns

## Troubleshooting

### No Logs Appearing
1. Check log file permissions
2. Verify logging configuration
3. Check disk space
4. Verify Reverb service is running

### Performance Impact
- Logging adds minimal overhead
- Use log rotation to manage disk space
- Consider reducing log level in production if needed

### Log Analysis
Use tools like `grep`, `awk`, or log analysis tools to:
- Filter by specific events
- Analyze error patterns
- Track user activity
- Monitor system health

## Security Considerations

- Logs may contain sensitive data (user IDs, message previews)
- Implement proper log access controls
- Consider log encryption for sensitive environments
- Regular log cleanup and rotation
