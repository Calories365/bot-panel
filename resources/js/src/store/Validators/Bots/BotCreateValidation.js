import { BaseValidator } from "@/store/Validators/BaseValidator.js";

export class BotCreateValidator extends BaseValidator {
    validate() {
        if (!this.data.name) {
            this.addError("name", "Имя обязательно");
        }
        if (!this.data.token) {
            this.addError("token", "Токен обязателен");
        }
        if (!this.data.type_id) {
            this.addError("type_id", "Тип бота обязателен");
        }
        if (this.data.type_id.type_id === 1 && !this.data.message) {
            this.addError(
                "message",
                'Сообщение обязательно для типа "Default"'
            );
        }
        if (this.data.type_id.type_id === 2 && !this.data.web_hook) {
            this.addError("web_hook", 'Вебхук обязателен для типа "Approval"');
        }
    }
}
