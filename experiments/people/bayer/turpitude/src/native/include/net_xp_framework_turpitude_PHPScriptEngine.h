/* DO NOT EDIT THIS FILE - it is machine generated */
#include <jni.h>
/* Header for class net_xp_framework_turpitude_PHPScriptEngine */

#ifndef _Included_net_xp_framework_turpitude_PHPScriptEngine
#define _Included_net_xp_framework_turpitude_PHPScriptEngine
#ifdef __cplusplus
extern "C" {
#endif
/*
 * Class:     net_xp_framework_turpitude_PHPScriptEngine
 * Method:    startUp
 * Signature: ()V
 */
JNIEXPORT void JNICALL Java_net_xp_1framework_turpitude_PHPScriptEngine_startUp
  (JNIEnv *, jobject);

/*
 * Class:     net_xp_framework_turpitude_PHPScriptEngine
 * Method:    shutDown
 * Signature: ()V
 */
JNIEXPORT void JNICALL Java_net_xp_1framework_turpitude_PHPScriptEngine_shutDown
  (JNIEnv *, jobject);

/*
 * Class:     net_xp_framework_turpitude_PHPScriptEngine
 * Method:    compilePHP
 * Signature: (Ljava/lang/String;)Ljava/lang/Object;
 */
JNIEXPORT jobject JNICALL Java_net_xp_1framework_turpitude_PHPScriptEngine_compilePHP
  (JNIEnv *, jobject, jstring);

#ifdef __cplusplus
}
#endif
#endif
