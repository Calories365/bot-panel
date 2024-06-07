import {mutationTypes} from "@/store/modules/auth.js";

export class BaseValidator {
    constructor(data, commit, dispatch) {
        this.data = data;
        this.commit = commit;
        this.dispatch = dispatch;
        this.errors = {};
    }

    validate() {
    }

    addError(key, message) {
        this.errors[key] = message;
    }

    hasErrors() {
        return Object.keys(this.errors).length > 0;
    }

    getErrors() {
        return this.errors;
    }

    async validateAndDispatchErrors() {
        this.validate();

        if (this.hasErrors()) {
            Object.values(this.getErrors()).forEach(error => {
                this.dispatch('addError', error, {root: true});
            });
            throw new Error();
        }
    }
}

