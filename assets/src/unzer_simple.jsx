import unzerRegisterSimpleMethod from './util/register-simple-method';
import simpleMethods from './util/simple-methods';

for(const simpleMethod of simpleMethods) {
    unzerRegisterSimpleMethod(simpleMethod);
}