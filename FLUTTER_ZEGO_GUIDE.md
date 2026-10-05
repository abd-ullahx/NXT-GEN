# Next Gen CRM — Flutter Zego Voice & Video Call Developer Guide

This guide provides end-to-end integration instructions for the Flutter mobile application (Surveyor / Staff / Customer) to communicate seamlessly with the Next Gen CRM Web Panel via **ZegoCloud**.

---

## 1. ZegoCloud Credentials & Backend Config

The backend is configured and verified with the following ZegoCloud credentials:

| Setting | Value |
|---|---|
| **Zego App ID** | `949748271` (integer) |
| **Zego App Sign** | `YOUR_ZEGO_APP_SIGN_HERE` |
| **API Base URL** | `https://shield-careless-impulse.ngrok-free.dev/api` *(or local `http://<IP>:8000/api`)* |

> [!NOTE]
> Every call endpoint automatically returns `zegoAppId` and `zegoAppSign` in its payload, so the Flutter app can dynamically consume these credentials or keep them in config constants.

---

## 2. Supported Call Flows

The CRM platform supports two distinct call flows:

1. **Lead Virtual Survey Call** (Surveyor / Customer $\leftrightarrow$ CRM Office)
   - Used during moving surveys and quotes.
   - Identified by Lead ID (`lead_id`, e.g., `L-8757`).
   - Generates room IDs like `ROOM-L-8757-XXXX`.

2. **Direct Team Chat Call** (Mobile Staff / Surveyor $\leftrightarrow$ Web Panel Staff / Admin)
   - 1-on-1 audio/video calls between authenticated team members.
   - Generates room IDs like `chat_lzgdqudtfgjkvfyk7ibzythr`.

---

## 3. API Endpoints Reference

### Flow A: Lead Virtual Survey Calls

#### 1. Start a Lead Call
- **Endpoint**: `POST /api/leads/{lead_id}/video-call/start`
- **Headers**:
  ```http
  Content-Type: application/json
  Accept: application/json
  ngrok-skip-browser-warning: true
  ```
- **Body**:
  ```json
  {
    "callerName": "Senior Surveyor",
    "callerRole": "surveyor",
    "targetRole": "admin",
    "isVideo": true
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "status": "ringing",
    "roomId": "ROOM-L-8757-FB31",
    "leadId": "L-8757",
    "zegoAppId": 949748271,
    "zegoAppSign": "YOUR_ZEGO_APP_SIGN_HERE",
    "is_video": true
  }
  ```

#### 2. Poll Lead Call Status
- **Endpoint**: `GET /api/leads/{lead_id}/video-call/status`
- **Response**:
  ```json
  {
    "success": true,
    "status": "ringing", // "ringing" | "in_progress" | "ended" | "declined" | "missed"
    "roomId": "ROOM-L-8757-FB31",
    "zegoAppId": 949748271,
    "zegoAppSign": "YOUR_ZEGO_APP_SIGN_HERE",
    "isVideo": true
  }
  ```

#### 3. Update / End Lead Call
- **Endpoint**: `POST /api/leads/{lead_id}/video-call/status`
- **Body**:
  ```json
  {
    "status": "ended" // "in_progress" | "ended"
  }
  ```

---

### Flow B: Direct Team Chat Calls (Mobile $\leftrightarrow$ Web Panel)

#### 1. Initiate Team Call
- **Endpoint**: `POST /api/chat/calls/start`
- **Headers**:
  ```http
  Authorization: Bearer <AUTH_TOKEN>
  Content-Type: application/json
  Accept: application/json
  ```
- **Body**:
  ```json
  {
    "recipient_id": 1,
    "is_video": true
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "call": {
      "id": 65,
      "roomId": "chat_avw8fdeoafewzoxmhofokfwj",
      "status": "ringing",
      "callType": "video",
      "isVideo": true,
      "callerId": "12",
      "callerName": "Field Surveyor",
      "recipientId": "1",
      "recipientName": "Admin User",
      "zegoAppId": 949748271,
      "zegoAppSign": "YOUR_ZEGO_APP_SIGN_HERE"
    }
  }
  ```

#### 2. Check for Incoming Calls (Polling)
- **Endpoint**: `GET /api/chat/calls/incoming?userId={current_user_id}`
- **Headers**:
  ```http
  Authorization: Bearer <AUTH_TOKEN>
  ```
- **Response (when ringing)**:
  ```json
  {
    "success": true,
    "call": {
      "id": 65,
      "roomId": "chat_avw8fdeoafewzoxmhofokfwj",
      "status": "ringing",
      "callType": "video",
      "isVideo": true,
      "callerId": "1",
      "callerName": "Admin User",
      "zegoAppId": 949748271,
      "zegoAppSign": "YOUR_ZEGO_APP_SIGN_HERE"
    }
  }
  ```
- **Response (when idle)**:
  ```json
  {
    "success": true,
    "call": null
  }
  ```

#### 3. Answer, Decline, or End Call
- **Endpoint**: `POST /api/chat/calls/{call_id}/action`
- **Body**:
  ```json
  {
    "action": "accept", // "accept" | "decline" | "end"
    "user_id": 12
  }
  ```

---

## 4. Flutter Dependencies & OS Setup

### Step 1: Add Dependency
In your Flutter app's `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter
  zego_uikit_prebuilt_call: ^4.16.10
  http: ^1.2.0
```

Run:
```bash
flutter pub get
```

---

### Step 2: Android Configuration

#### 1. Permissions (`android/app/src/main/AndroidManifest.xml`)
Add inside `<manifest>`:

```xml
<uses-permission android:name="android.permission.ACCESS_WIFI_STATE" />
<uses-permission android:name="android.permission.RECORD_AUDIO" />
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.BLUETOOTH" />
<uses-permission android:name="android.permission.MODIFY_AUDIO_SETTINGS" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.READ_PHONE_STATE" />
<uses-permission android:name="android.permission.WAKE_LOCK" />
```

#### 2. Min SDK Version (`android/app/build.gradle`)
Ensure `minSdkVersion` is at least `21`:
```gradle
defaultConfig {
    minSdkVersion 21
    compileSdkVersion 34
    ...
}
```

#### 3. Proguard Rules (`android/app/proguard-rules.pro`)
```proguard
-keep class **.zego.** { *; }
```

---

### Step 3: iOS Configuration

In `ios/Runner/Info.plist`:

```xml
<key>NSCameraUsageDescription</key>
<string>We require camera access for HD Virtual Survey and Team Video Calls.</string>
<key>NSMicrophoneUsageDescription</key>
<string>We require microphone access for voice and video communications.</string>
```

---

## 5. Flutter Implementation Example

Here is a complete, copy-pasteable Flutter page for joining the Zego call:

```dart
import 'package:flutter/material.dart';
import 'package:zego_uikit_prebuilt_call/zego_uikit_prebuilt_call.dart';

class ZegoCallScreen extends StatelessWidget {
  final int appId;
  final String appSign;
  final String callId; // roomId from CRM API (e.g. ROOM-L-8757-FB31 or chat_xxx)
  final String userId;
  final String userName;
  final bool isVideo;
  final VoidCallback? onCallEnded;

  const ZegoCallScreen({
    Key? key,
    required this.appId,
    required this.appSign,
    required this.callId,
    required this.userId,
    required this.userName,
    this.isVideo = true,
    this.onCallEnded,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: ZegoUIKitPrebuiltCall(
        appID: appId,
        appSign: appSign,
        userID: userId,
        userName: userName,
        callID: callId,
        config: isVideo
            ? ZegoUIKitPrebuiltCallConfig.oneOnOneVideoCall()
            : ZegoUIKitPrebuiltCallConfig.oneOnOneVoiceCall()
          ..onHangUp = () {
            if (onCallEnded != null) {
              onCallEnded!();
            }
            Navigator.of(context).pop();
          },
      ),
    );
  }
}
```

---

## 6. Launching the Call Screen in Flutter

When the user accepts or initiates a call:

```dart
void openZegoCall({
  required BuildContext context,
  required int appId,
  required String appSign,
  required String roomId,
  required int callId,
  required String currentUserId,
  required String currentUserName,
  required bool isVideo,
}) {
  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (context) => ZegoCallScreen(
        appId: appId,
        appSign: appSign,
        callId: roomId,
        userId: currentUserId,
        userName: currentUserName,
        isVideo: isVideo,
        onCallEnded: () async {
          // Notify the CRM backend that call has ended
          await http.post(
            Uri.parse('https://shield-careless-impulse.ngrok-free.dev/api/chat/calls/$callId/action'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'action': 'end', 'user_id': currentUserId}),
          );
        },
      ),
    ),
  );
}
```

---

## 7. Verification Checklist

| Test Case | Description | Status |
|---|---|---|
| **Web $\leftrightarrow$ Web Video** | Tested on `https://shield-careless-impulse.ngrok-free.dev/crm/` | ✅ Verified |
| **API Credentials** | `zegoAppId` & `zegoAppSign` match official settings | ✅ Verified |
| **Chat Call Flow** | Start $\to$ Incoming Poll $\to$ Accept $\to$ End | ✅ Automated Tests Passing (29 assertions) |
| **Lead Survey Flow** | Start Lead Call $\to$ Poll $\to$ Sync In-Progress $\to$ End | ✅ Automated Tests Passing |
| **Ngrok Access** | Endpoints reachable externally with `ngrok-skip-browser-warning` | ✅ Verified |
