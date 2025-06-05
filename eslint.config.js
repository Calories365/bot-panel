import js from "@eslint/js"
import globals from "globals"
import pluginVue from "eslint-plugin-vue"
import { defineConfig } from "eslint/config"

export default defineConfig([
    {
        ignores: [
            "node_modules",
            "public",
            "resources/css/admin.css",
            "resources/js/admin.js",
            "public/build/**/*",
        ],
    },
    {
        files: ["**/*.{js,mjs,cjs,vue}"],
        languageOptions: {
            globals: globals.browser,
        },
        plugins: {
            js,
            vue: pluginVue,
        },
        rules: {
        },
        extends: [
            js.configs.recommended,
            pluginVue.configs["flat/essential"],
        ],
    },
])

