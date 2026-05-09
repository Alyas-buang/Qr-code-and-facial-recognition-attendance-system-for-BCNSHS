(function (global) {
    "use strict";

    function sleep(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function toErrorMessage(err) {
        if (err && typeof err === "object") {
            if (typeof err.message === "string" && err.message.trim() !== "") {
                return err.message;
            }
            if (typeof err.name === "string" && err.name.trim() !== "") {
                return err.name;
            }
        }
        if (typeof err === "string" && err.trim() !== "") {
            return err;
        }
        return "Unknown error";
    }

    async function fetchWithRetry(url, attempts, baseDelayMs) {
        var tries = Number.isFinite(attempts) ? attempts : 3;
        var delay = Number.isFinite(baseDelayMs) ? baseDelayMs : 250;
        var lastError = null;

        for (var i = 1; i <= tries; i++) {
            try {
                var res = await fetch(url, { cache: "force-cache" });
                if (!res.ok) {
                    throw new Error("HTTP " + res.status);
                }
                return true;
            } catch (err) {
                lastError = err;
                await sleep(delay * i);
            }
        }

        throw lastError || new Error("Failed to fetch model file");
    }

    async function warmModelAssets(modelUrl, modelFiles, attempts) {
        await Promise.all(
            modelFiles.map(function (file) {
                return fetchWithRetry(modelUrl + "/" + file, attempts || 3, 250);
            })
        );
    }

    async function loadFaceApiModels(options) {
        var modelUrl = options.modelUrl;
        var modelFiles = options.modelFiles || [];
        var loaders = options.loaders || [];
        var maxAttempts = Number.isFinite(options.maxAttempts) ? options.maxAttempts : 3;
        var onAttempt = options.onAttempt;
        var onSuccess = options.onSuccess;
        var onFailure = options.onFailure;

        var lastError = null;

        for (var attempt = 1; attempt <= maxAttempts; attempt++) {
            try {
                if (typeof onAttempt === "function") {
                    onAttempt(attempt, maxAttempts);
                }
                await warmModelAssets(modelUrl, modelFiles, 3);
                await Promise.all(loaders.map(function (loader) { return loader(modelUrl); }));
                if (typeof onSuccess === "function") {
                    onSuccess();
                }
                return true;
            } catch (err) {
                lastError = err;
                await sleep(500 * attempt);
            }
        }

        if (typeof onFailure === "function") {
            onFailure(lastError);
        }
        return false;
    }

    global.FaceModelLoader = {
        sleep: sleep,
        toErrorMessage: toErrorMessage,
        fetchWithRetry: fetchWithRetry,
        warmModelAssets: warmModelAssets,
        loadFaceApiModels: loadFaceApiModels
    };
})(window);
