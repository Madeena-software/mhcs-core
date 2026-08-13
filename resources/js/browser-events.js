export class EventEmitter {
    #listeners = new Map();

    on(event, listener) {
        const listeners = this.#listeners.get(event) ?? [];
        listeners.push(listener);
        this.#listeners.set(event, listeners);
        return this;
    }

    once(event, listener) {
        const wrapped = (...args) => {
            this.off(event, wrapped);
            listener(...args);
        };
        return this.on(event, wrapped);
    }

    off(event, listener) {
        const listeners = this.#listeners.get(event) ?? [];
        this.#listeners.set(event, listeners.filter((candidate) => candidate !== listener));
        return this;
    }

    removeListener(event, listener) {
        return this.off(event, listener);
    }

    emit(event, ...args) {
        for (const listener of this.#listeners.get(event) ?? []) {
            listener(...args);
        }
        return this.#listeners.has(event);
    }
}

export default { EventEmitter };
