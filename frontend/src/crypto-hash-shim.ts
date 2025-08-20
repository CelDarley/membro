import * as realCrypto from 'node:crypto'

export const hash = (alg: string) => realCrypto.createHash(alg)

export * from 'node:crypto'

export default Object.assign({}, realCrypto as any, { hash }) 