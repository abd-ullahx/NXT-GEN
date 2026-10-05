# Backend Developer Guide: Background VoIP Calls via FCM High-Priority Push

## Overview
When the Web CRM or any user calls a mobile app user (`POST /api/chat/calls/start`), if the mobile device is in **Background**, **Phone Screen Locked**, or **App Closed**, the phone will ring with a real WhatsApp-style **Full-Screen Incoming Call Ringing UI**.

---

## 1. Verified Architecture & Credentials

The Laravel backend is fully configured and connected to Firebase Cloud Messaging (FCM v1):

| Setting | Value | Status |
|---|---|---|
| **Firebase Project ID** | `next-gen-d9098` | ✅ Verified in `storage/app/firebase-service-account.json` |
| **OAuth2 Access Token** | Google Service Account Auth | ✅ Verified & Live |
| **FCM v1 API Endpoint** | `https://fcm.googleapis.com/v1/projects/next-gen-d9098/messages:send` | ✅ Verified |
| **Database Support** | `users.fcm_token`, `users.device_type` | ✅ Active in MySQL DB |
| **Zego App ID** | `949748271` | ✅ Configured |
| **Zego App Sign** | `YOUR_ZEGO_APP_SIGN_HERE` | ✅ Configured |

---

## 2. Step 1: Save User Device FCM Token

As soon as the user logs in on the Flutter app, Flutter must send the device FCM token:

* **Endpoint:** `POST /api/user/fcm-token`
* **Headers:**
  ```http
  Authorization: Bearer <user_sanctum_token>
  Content-Type: application/json
  Accept: application/json
  ```
* **Request Body:**
  ```json
  {
    "fcm_token": "eK...device_token_from_mobile...",
    "device_type": "android"
  }
  ```
* **Response:**
  ```json
  {
    "message": "FCM token updated successfully."
  }
  ```

---

## 3. Step 2: High-Priority FCM Push on Call Creation

When a call is started (`POST /api/chat/calls/start`), the backend automatically sends a **High-Priority Data-Only Message** to the recipient's registered device FCM token:

### FCM Request Payload (Automated by Backend):
```json
{
  "message": {
    "token": "RECIPIENT_FCM_DEVICE_TOKEN",
    "android": {
      "priority": "high",
      "ttl": "45s"
    },
    "apns": {
      "headers": {
        "apns-priority": "10",
        "apns-push-type": "background"
      },
      "payload": {
        "aps": {
          "content-available": 1
        }
      }
    },
    "data": {
      "type": "incoming_call",
      "call_id": "135",
      "room_id": "chat_lzqbpeozm3lxc1t4vtbsvbcj",
      "caller_id": "1",
      "caller_name": "Admin Office",
      "caller_email": "admin@nextgenrelocation.co.uk",
      "caller_role": "admin",
      "is_video": "true",
      "status": "ringing",
      "zego_app_id": "949748271",
      "zego_app_sign": "YOUR_ZEGO_APP_SIGN_HERE"
    }
  }
}
```

> [!IMPORTANT]
> **Data-Only Payload**: No `"notification": { "title": "..." }` key is present, preventing generic system notification banners and allowing the Flutter background handler to directly wake the device, trigger full-screen intent, and play custom ringtones.

---

## 4. Step 3: Call Cancellation / Hangup Dismissal Push

If the caller cancels the call before the recipient answers, or if the call times out (>35s), the backend automatically dispatches a cancellation push so the mobile app stops ringing:

```json
{
  "message": {
    "token": "RECIPIENT_FCM_DEVICE_TOKEN",
    "android": {
      "priority": "high"
    },
    "data": {
      "type": "call_cancelled",
      "call_id": "135",
      "status": "cancelled"
    }
  }
}
```

---

## 5. Step 4: Call Action Endpoints

When the mobile user answers, declines, or ends the call:

* **Accept Call**: `POST /api/chat/calls/{call_id}/action` with body:
  ```json
  { "action": "accept", "user_id": 12 }
  ```
* **Decline Call**: `POST /api/chat/calls/{call_id}/action` with body:
  ```json
  { "action": "decline", "user_id": 12 }
  ```
* **End Call (Hangup)**: `POST /api/chat/calls/{call_id}/action` with body:
  ```json
  { "action": "end", "user_id": 12 }
  ```
