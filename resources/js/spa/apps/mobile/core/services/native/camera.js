/*
|------------------------------------------------------------------
| Native image capture
|------------------------------------------------------------------
| <input type="file" accept="image/*"> already opens the system picker in a
| Capacitor WebView, so this is NOT here to make uploads work -- they already
| do. What the plugin adds is: an in-app capture path that does not leave the
| app, quality/size control before upload (these are social posts over Indian
| mobile data), and an explicit permission request rather than relying on the
| WebView's chooser to ask.
|
| Everything falls back to the existing file input if it fails, so a denied
| permission never blocks posting.
*/

import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';

/**
 * Opens the native camera/gallery chooser and returns a File the existing
 * upload path can consume unchanged.
 *
 * @returns {Promise<File|null>} null when the user cancels or capture fails.
 */
export async function pickImage() {
    let photo;

    try {
        photo = await Camera.getPhoto({
            // Prompt shows a Camera / Photos chooser, which is what the file
            // input did -- minus the browser chrome.
            source: CameraSource.Prompt,
            resultType: CameraResultType.Uri,
            quality: 85,
            // Long edge cap. Full-resolution phone photos are 4-8 MB and the
            // server re-encodes to webp anyway; sending them raw just burns the
            // user's data.
            width: 2048,
            correctOrientation: true,
            allowEditing: false,
        });
    } catch {
        // Cancelled, or no permission. The caller falls back to the file input.
        return null;
    }

    if (! photo?.webPath) {
        return null;
    }

    const blob = await fetch(photo.webPath).then((response) => response.blob());
    const extension = photo.format || 'jpeg';

    return new File([blob], `capture-${Date.now()}.${extension}`, {
        type: blob.type || `image/${extension}`,
    });
}
