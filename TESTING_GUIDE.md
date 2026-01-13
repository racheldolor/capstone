# Testing Guide - Profile Edit System

## ✅ System Features (After Fix)

### 1. **NO AUTO-SAVE** - Lahat ng changes ay STAGED muna
- Photo upload/delete
- Participation records add/delete
- Affiliation records add/delete
- Profile information edits

### 2. **Single Save Point** - Lahat mag-save sa ONE click lang
- Pag nag-click ng "Save Profile" button
- Confirmation modal lalabas
- Pag "Yes, Save Changes" - lahat ng pending changes ay isasave sa database

---

## 🧪 Testing Steps

### Test 1: Photo Upload (NO AUTO-SAVE)
```
1. Login as student
2. Click "Edit Profile"
3. Click "📷 Upload Photo"
4. Select image file
5. ✅ EXPECTED: 
   - Preview shows
   - Button says "📷 Photo Selected (Pending)" - YELLOW
   - Alert: "📸 Photo selected! Click 'Save Profile' to upload."
   - ❌ HINDI MAG-UPLOAD SA DATABASE YET
6. Click "Save Profile" → "Yes, Save Changes"
7. ✅ EXPECTED: Photo saves to database
```

### Test 2: Photo Delete (NO AUTO-SAVE)
```
1. Edit mode ON
2. Click "🗑️ Delete Photo"
3. Confirm deletion
4. ✅ EXPECTED:
   - Default avatar shows
   - Button says "🗑️ Marked for Deletion (Pending)" - RED
   - Alert: "🗑️ Photo marked for deletion! Click 'Save Profile' to confirm."
   - ❌ HINDI MAG-DELETE SA DATABASE YET
5. Click "Save Profile" → "Yes, Save Changes"
6. ✅ EXPECTED: Photo deletes from database
```

### Test 3: Add Participation Record (NO AUTO-SAVE)
```
1. Edit mode ON
2. Click "Add Participation"
3. Fill in Date, Event Name, Venue, Level
4. Click "Add" button
5. ✅ EXPECTED:
   - Row background = YELLOW
   - Button says "Added (Pending)" - YELLOW
   - Alert: "✅ Participation record will be added when you click Save Profile"
   - ❌ HINDI MAG-INSERT SA DATABASE YET
6. Open browser console (F12)
7. Check console.log:
   - "✅ Participation staged for add"
   - "Total pending participation adds: 1"
8. Click "Save Profile" → "Yes, Save Changes"
9. ✅ EXPECTED: Record saves to student_participation_records table
```

### Test 4: Delete Participation Record (NO AUTO-SAVE)
```
1. Edit mode ON
2. Click "Delete" on existing participation
3. ✅ EXPECTED:
   - Row background = RED
   - Text has strikethrough
   - Button says "Undo" - BLUE
   - ❌ HINDI MAG-DELETE SA DATABASE YET
4. Check console: "✅ Participation staged for deletion"
5. Click "Save Profile" → "Yes, Save Changes"
6. ✅ EXPECTED: Record deletes from database
```

### Test 5: Multiple Changes at Once
```
1. Edit mode ON
2. Upload photo
3. Add 2 participation records
4. Delete 1 affiliation record
5. Edit first name
6. ✅ EXPECTED: All pending, NO database changes yet
7. Click "Save Profile" → "Yes, Save Changes"
8. ✅ EXPECTED: ALL changes save to database in ONE operation
9. Check console logs:
   - "=== SAVING PROFILE ==="
   - "Pending Changes: { participation: {...}, affiliation: {...}, photo: {...} }"
   - "Photo operation completed"
   - "Sending profile update to server..."
   - "Server response: { success: true }"
```

### Test 6: Cancel Without Saving
```
1. Edit mode ON
2. Make multiple changes (add, delete, upload photo)
3. Click "Cancel" button
4. Confirm cancel
5. ✅ EXPECTED:
   - All pending changes cleared
   - Page reloads with original data
   - Photo buttons reset to normal
   - ❌ NO changes saved to database
```

### Test 7: Undo Delete Before Save
```
1. Edit mode ON
2. Delete participation record (red row)
3. Click "Undo" button
4. ✅ EXPECTED:
   - Row returns to normal
   - ID removed from toDelete array
5. Click "Save Profile"
6. ✅ EXPECTED: Record still exists in database (not deleted)
```

---

## 🔍 Debugging Checklist

### Browser Console (Press F12)
When testing, check console for these logs:

**During Add/Delete Operations:**
```
✅ Participation staged for add: {date: "...", event_name: "..."}
Total pending participation adds: 1
✅ Participation staged for deletion, ID: 123
Total pending participation deletes: 1
```

**During Save Operation:**
```
=== SAVING PROFILE ===
Pending Changes: {
  participation: { toAdd: [...], toDelete: [...] },
  affiliation: { toAdd: [...], toDelete: [...] },
  photo: { action: "upload", file: File {...} }
}
Data to send: { first_name: "...", pendingChanges: {...} }
Photo operation completed: { success: true }
Sending profile update to server...
Server response status: 200
Server response data: { success: true, message: "..." }
```

### Database Verification

**After Save, check tables:**
```sql
-- Check participation records
SELECT * FROM student_participation_records 
WHERE student_id = [YOUR_STUDENT_ID] 
ORDER BY created_at DESC;

-- Check affiliation records
SELECT * FROM student_affiliation_records 
WHERE student_id = [YOUR_STUDENT_ID]
ORDER BY created_at DESC;

-- Check profile photo
SELECT profile_photo FROM student_artists 
WHERE id = [YOUR_STUDENT_ID];

-- Check profile updates
SELECT * FROM student_artists 
WHERE id = [YOUR_STUDENT_ID];
```

---

## ❌ Common Issues & Solutions

### Issue 1: "Saving All Changes..." stuck
**Solution:** Check browser console for errors. Likely network issue or server error.

### Issue 2: Changes not saving to database
**Solution:** 
1. Check console logs - may error sa server?
2. Check update_profile.php error logs
3. Verify pendingChanges object has data

### Issue 3: Photo still auto-saving
**Solution:**
1. Hard refresh browser: `Ctrl + Shift + R`
2. Clear browser cache
3. Check if edit mode is ON (buttons should be visible)

### Issue 4: Can add/delete in view mode
**Solution:**
1. Photo buttons should only work in edit mode
2. Check console for: "⚠️ Please click 'Edit Profile' first"

---

## 📊 Expected Behavior Summary

| Action | Edit Mode OFF | Edit Mode ON (Before Save) | After Save Profile |
|--------|--------------|---------------------------|-------------------|
| Upload Photo | Alert: Need edit mode | Preview + "Pending" button | Saves to database + uploads file |
| Delete Photo | Alert: Need edit mode | Default avatar + "Pending" | Deletes from database |
| Add Participation | Hidden | Yellow row + "Added (Pending)" | Inserts to database |
| Delete Participation | Hidden | Red row + strikethrough | Deletes from database |
| Edit Fields | Not editable | Editable fields | Updates in database |

---

## ✅ Success Criteria

**ALL of these must be TRUE:**

1. ✅ NO database changes when adding/deleting records (only staging)
2. ✅ NO photo upload/delete until Save Profile clicked
3. ✅ Pending changes tracked in `pendingChanges` object
4. ✅ Visual indicators: Yellow (pending add), Red (pending delete)
5. ✅ Console logs show staging operations
6. ✅ Save Profile processes ALL pending changes at once
7. ✅ Cancel clears ALL pending changes without saving
8. ✅ After save, changes appear in database tables
9. ✅ Can edit multiple times without auto-save
10. ✅ Photo operations only work in edit mode

---

## 🔧 If Still Having Issues

**Run these commands to verify setup:**

```bash
# Check if tables exist
mysql -u root capstone_culture_arts -e "SHOW TABLES LIKE 'student_%';"

# Check table structure
mysql -u root capstone_culture_arts -e "DESC student_participation_records;"
mysql -u root capstone_culture_arts -e "DESC student_affiliation_records;"

# Check if there are any existing records
mysql -u root capstone_culture_arts -e "SELECT COUNT(*) FROM student_participation_records;"
```

**Enable PHP error logging:**
Add to `php.ini`:
```ini
error_reporting = E_ALL
display_errors = On
log_errors = On
error_log = "c:/xampp/php/logs/php_error_log"
```

**Restart XAMPP:**
1. Stop Apache
2. Stop MySQL
3. Start MySQL
4. Start Apache

---

## 📞 Support

If tests still fail after following this guide:
1. Check browser console (F12) for JavaScript errors
2. Check `c:/xampp/php/logs/php_error_log` for PHP errors
3. Check `c:/xampp/mysql/data/[database]/*.err` for MySQL errors
4. Provide console logs and error messages for debugging

---

**Last Updated:** January 13, 2026
**System Version:** 3.1 - Staged Changes Implementation
